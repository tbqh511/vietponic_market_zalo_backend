<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * JWT middleware dành riêng cho Zalo Mini App APIs.
 *
 * Khác với JwtMiddleware (dành cho BDS app, check api_token),
 * middleware này chỉ cần verify JWT token hợp lệ + customer tồn tại và active.
 * Token được tạo bởi ZaloApiController::authenticate().
 */
class ZaloJwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Lấy token từ header Authorization: Bearer xxx
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'error' => true,
                    'message' => 'Authorization Token not found'
                ], 401);
            }

            // Decode payload để lấy customer_id
            $payload = JWTAuth::decode($token);
            $customerId = $payload->get('customer_id');

            if (!$customerId) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid token: missing customer_id'
                ], 401);
            }

            // Verify customer tồn tại và active
            $customer = Customer::find($customerId);

            if (!$customer) {
                return response()->json([
                    'error' => true,
                    'message' => 'Customer not found'
                ], 401);
            }

            if ($customer->isActive == 0) {
                return response()->json([
                    'error' => true,
                    'message' => 'Tài khoản đã bị vô hiệu hoá, vui lòng liên hệ admin'
                ], 401);
            }

            // Gắn customer_id vào request để controller sử dụng
            $request->attributes->set('zalo_customer_id', $customerId);
            $request->attributes->set('zalo_customer', $customer);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Token is Invalid'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Token is Expired'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Authorization failed: ' . $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}
