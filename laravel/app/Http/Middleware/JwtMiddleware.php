<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::getToken();
            if($token == ''){
                return response()->json([
                    'error' => true,
                    'message' => 'Authorization Token not found'
                ],401);
            }
            $payload = JWTAuth::decode($token);
            $user_id = $payload->get('customer_id');
            $res = Customer::find($user_id);
            if(!empty($res)){
                if($res->api_token != $token){
                    return response()->json([
                        'error' => true,
                        'message' => 'Unauthorized access'
                    ],401);
                } else {
                    if($res->isActive == 0){
                        return response()->json([
                            'error' => true,
                            'message' => 'your account has been deactive! please contact admin'
                        ],401);
                    }
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Unauthorized access'
                ],401);
            }

            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return response()->json([
                    'error' => true,
                    'message' => 'Token is Invalid'
                ],401);
            } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return response()->json([
                    'error' => true,
                    'message' => 'Token is Expired'
                ],401);
            } else{
                return response()->json([
                    'error' => true,
                    'message' => 'Authorization Token not found'
                ],401);
            }
        }
        return $next($request);
    }
}
