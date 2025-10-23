<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (! $user || ! ($user->is_admin ?? false)) {
            abort(403, 'Unauthorized');
        }
        return $next($request);
    }
}
