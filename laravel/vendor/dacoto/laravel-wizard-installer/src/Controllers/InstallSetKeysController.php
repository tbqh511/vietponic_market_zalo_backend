<?php

namespace dacoto\LaravelWizardInstaller\Controllers;

use dacoto\LaravelWizardInstaller\Exceptions\CantGenerateKeyException;
use dacoto\SetEnv\Facades\SetEnv;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class InstallSetKeysController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        try {
            Artisan::call('key:generate', ['--force' => true, '--show' => true]);
            if (empty(SetEnv::getValue('APP_KEY'))) {
                SetEnv::setKey('APP_KEY', trim(str_replace('"', '', Artisan::output())));
                SetEnv::save();
            }
            if (empty(SetEnv::getValue('APP_KEY'))) {
                throw new CantGenerateKeyException();
            }
        } catch (Exception $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        Artisan::call('storage:link', ['--force' => true]);

        try {
            foreach (config('installer.commands', []) as $command) {
                Artisan::call($command);
            }
        } catch (Exception $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        return redirect()->route('LaravelWizardInstaller::install.finish');
    }
}
