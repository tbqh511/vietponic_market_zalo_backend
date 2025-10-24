<?php

namespace dacoto\LaravelWizardInstaller\Controllers;

use dacoto\SetEnv\Facades\SetEnv;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InstallPurchaseController extends Controller
{
    /**
     * Set database settings
     *
     * @return Application|Factory|RedirectResponse|View
     */
    public function index()
    {
        if ((new InstallServerController())->check() === false || (new InstallFolderController())->check() === false) {
            return redirect()->route('LaravelWizardInstaller::install.folders');
        }
        return view('installer::steps.purchase');
    }

    /**
     * Test database and set keys in .env
     *
     * @param  Request  $request
     * @return Application|Factory|RedirectResponse|View
     */
    public function setPurchase(Request $request)
    {
        try {
            $app_url = (string) url('/');
            $app_url = preg_replace('#^https?://#i', '', $app_url);

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://wrteam.in/validator/ebroker_validator?purchase_code=' . $request->input('purchase_code') . '&domain_url=' . $app_url . '',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response, true);

            if ($response['error']) {
                return view('installer::steps.purchase', ['error' => $response["message"]]);
            } else {
                SetEnv::setKey('APPSECRET', $request->input('purchase_code'));
                SetEnv::save();
                return redirect()->route('LaravelWizardInstaller::install.database');
            }
        } catch (Exception $e) {
            $values = [
                'purchase_code' => $request->get("purchase_code"),
            ];
            return view('installer::steps.purchase', ['values' => $values, 'error' => $e->getMessage()]);
        }
    }
}
