<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\Farm;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * EnsureFarmPartner — gác cổng cho cụm route Farm Partner Hub.
 *
 * Khác với ZaloJwtMiddleware (chỉ cần JWT hợp lệ + customer active),
 * middleware này yêu cầu customer thực sự là farm partner đã được duyệt
 * VÀ đã được admin gán làm owner của một farm cụ thể.
 *
 * Lý do KHÔNG tin claim `is_farm_partner` trong JWT:
 *   - JWT có TTL ~30 phút. Trong khoảng đó admin có thể suspend partner,
 *     huỷ phê duyệt, hoặc đổi owner_customer_id → claim cũ vẫn pass.
 *   - Vì vậy mỗi request ta verify lại từ DB. Cost = 1-2 query, chấp nhận
 *     được cho cụm Farm Hub (volume thấp).
 *
 * Sau khi pass, middleware gắn 3 attributes vào request cho controller:
 *   - zalo_customer_id : int — id customer
 *   - zalo_customer    : Customer — model đầy đủ
 *   - farm             : Farm — model farm mà customer đang sở hữu
 *                        Controller chỉ cần $request->attributes->get('farm').
 *
 * Lifecycle lỗi:
 *   401 — thiếu/sai JWT, customer không tồn tại, customer bị vô hiệu hoá.
 *   403 — customer hợp lệ nhưng chưa phải farm partner (role/status), hoặc
 *         là farm partner nhưng chưa được gán farm (record Farm chưa tạo
 *         hoặc bị deactive). Cả hai đều coi là "không đủ quyền".
 */
class EnsureFarmPartner
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

            // Load customer từ DB — KHÔNG tin claim is_farm_partner trong JWT
            // (xem docblock class để hiểu lý do).
            $customer = Customer::find($customerId);

            if (!$customer) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Customer not found',
                ], 401);
            }

            if ((int) $customer->isActive === 0) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Tài khoản đã bị vô hiệu hoá, vui lòng liên hệ admin',
                ], 401);
            }

            // Verify role='farm_partner' AND farm_partner_status='approved'.
            // requested / suspended / none → 403.
            if (!$customer->isFarmPartner()) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Bạn không có quyền truy cập chức năng Farm Partner',
                ], 403);
            }

            // Verify customer có farm đang active. scopeActive yêu cầu
            // is_active=true AND approved_at NOT NULL.
            // Dùng query thay vì $customer->farm để áp được scope.
            $farm = Farm::active()
                ->where('owner_customer_id', $customer->id)
                ->first();

            if (!$farm) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Tài khoản farm partner chưa được gán farm hoặc farm đã bị vô hiệu hoá',
                ], 403);
            }

            // Gắn vào request cho controller downstream. Convention giống
            // ZaloJwtMiddleware để code dùng chung không phải biết middleware nào.
            $request->attributes->set('zalo_customer_id', $customerId);
            $request->attributes->set('zalo_customer', $customer);
            $request->attributes->set('farm', $farm);

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
