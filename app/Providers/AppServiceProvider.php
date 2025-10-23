<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix for older MySQL / MariaDB installations where the default
        // index length with utf8mb4 exceeds limits. Set default string length
        // to 191 so migrations that create varchar(255) indexed columns succeed.
        Schema::defaultStringLength(191);
    }
}
