<?php

namespace App\Http\Middleware;

use Closure;
// use Illuminate\Console\View\Components\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Alert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class DemoMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        //        echo $request->getRequestUri();
        $exclude_uri = array(
            '/login',
            '/logout'


        );

        // dd(Auth::user()->email != 'superadmin@gmail.com');
        if (env('DEMO_MODE') && Auth::user() && Auth::user()->email != 'superadmin@gmail.com') {
            // dd(!in_array($request->getRequestUri(), $exclude_uri));

            if (!$request->isMethod('get') && !in_array($request->getRequestUri(), $exclude_uri)) {



                if ($request->ajax()) {
                    $response['error'] = false;
                    $response['message'] = 'This is not allowed in the Demo Version';
                    return response()->json($response);
                } else if (request()->wantsJson() || Str::startsWith(request()->path(), 'api')) {
                    $response['error'] = false;
                    $response['message'] = 'This is not allowed in the Demo Version';
                    return response()->json($response);
                } else {
                    return back()->with('error', 'This is not allowed in the Demo Version');
                }
            }
        }
        return $next($request);
    }
}
