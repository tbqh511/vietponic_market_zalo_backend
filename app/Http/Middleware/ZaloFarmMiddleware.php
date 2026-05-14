<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Extends JWT authentication to require an active farm-partner record.
 * Regular customers (even with valid JWT) are rejected with 403.
 */
class ZaloFarmMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Authorization Token not found',
                ], 401);
            }

            $payload    = JWTAuth::decode($token);
            $customerId = $payload->get('customer_id');

            if (!$customerId) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Invalid token: missing customer_id',
                ], 401);
            }

            $customer = Customer::find($customerId);

            if (!$customer) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Customer not found',
                ], 401);
            }

            if ($customer->isActive == 0) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Tài khoản đã bị vô hiệu hoá, vui lòng liên hệ admin',
                ], 401);
            }

            if (!$customer->farmPartner()->where('status', 'active')->exists()) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Bạn không có quyền truy cập chức năng này',
                ], 403);
            }

            $request->attributes->set('zalo_customer_id', $customerId);
            $request->attributes->set('zalo_customer', $customer);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => true, 'message' => 'Token is Invalid'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['error' => true, 'message' => 'Token is Expired'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'Authorization failed: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
