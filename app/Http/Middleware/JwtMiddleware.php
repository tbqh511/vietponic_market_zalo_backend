<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::getToken();
            if (! $token) {
                return response()->json(['error' => true, 'message' => 'Authorization Token not found'], 401);
            }

            // get payload and user id
            $payload = JWTAuth::getPayload($token);
            $userId = $payload->get('customer_id') ?? $payload->get('sub');

            if (! $userId) {
                return response()->json(['error' => true, 'message' => 'Invalid token payload'], 401);
            }

            $user = User::find($userId);
            if (! $user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
            }

            // check stored api_token matches token to avoid old tokens
            if ($user->api_token && $user->api_token !== (string) $token) {
                return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
            }

            if (property_exists($user, 'isActive') && $user->isActive == 0) {
                return response()->json(['error' => true, 'message' => 'your account has been deactive! please contact admin'], 401);
            }

            // authenticate and set current user
            $authUser = JWTAuth::parseToken()->authenticate();
            // ensure parsed user matches looked up user (extra safety)
            if ($authUser && $authUser->id !== $user->id) {
                return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
            }

        } catch (TokenInvalidException $e) {
            return response()->json(['error' => true, 'message' => 'Token is Invalid'], 401);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => true, 'message' => 'Token is Expired'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => true, 'message' => 'Authorization Token not found'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
        }

        return $next($request);
    }
}
