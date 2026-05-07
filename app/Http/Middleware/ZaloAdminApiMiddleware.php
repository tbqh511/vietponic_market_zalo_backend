<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ZaloAdminApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $secret = env('ADMIN_API_SECRET');

        if (!$secret) {
            return response()->json(['error' => true, 'message' => 'Admin API not configured'], 500);
        }

        $header = $request->header('X-Admin-Secret');

        if (!$header || !hash_equals($secret, $header)) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
