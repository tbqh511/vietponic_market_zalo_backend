<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        // global middleware
        \App\Http\Middleware\AllowZaloOrigin::class,
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class ?? \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
    ];

    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class ?? \Illuminate\Auth\Middleware\Authenticate::class,
        'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
        'jwt.verify' => \App\Http\Middleware\JwtMiddleware::class,
        'is_admin' => \App\Http\Middleware\IsAdmin::class,
        'allow.zalo' => \App\Http\Middleware\AllowZaloOrigin::class,
    ];
}
