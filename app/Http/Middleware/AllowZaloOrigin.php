<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowZaloOrigin
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $origin = $request->header('Origin');
        $allowed = array_map('trim', explode(',', env('ALLOWED_ZALO_ORIGINS', 'https://h5.zdn.vn')));

        if ($origin && in_array($origin, $allowed)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        return $response;
    }
}
