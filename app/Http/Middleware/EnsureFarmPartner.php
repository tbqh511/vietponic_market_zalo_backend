<?php

namespace App\Http\Middleware;

use App\Http\Concerns\InteractsWithAccountStatus;
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
 *   401 — thiếu/sai JWT, customer không tồn tại.
 *   403 — customer bị vô hiệu hoá (code ACCOUNT_DISABLED), hoặc customer hợp lệ
 *         nhưng chưa phải farm partner (role/status), hoặc
 *         là farm partner nhưng chưa được gán farm (record Farm chưa tạo
 *         hoặc bị deactive). Cả hai đều coi là "không đủ quyền".
 */
class EnsureFarmPartner
{
    use InteractsWithAccountStatus;

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
                return $this->accountDisabledResponse();
            }

            // Verify role='farm_partner' AND farm_partner_status='approved'.
            // requested / suspended / none → 403. isFarmPartner() trả false cho
            // CẢ 'requested' lẫn 'suspended', nên branch theo status để chọn đúng
            // message + code (discriminator máy-đọc cho FE, không bắt FE parse
            // chuỗi tiếng Việt):
            //   - suspended → message "tạm dừng" THỐNG NHẤT (chung với farm
            //     is_active=false bên dưới) + code FARM_SUSPENDED.
            //   - còn lại (none/requested/role≠farm_partner) → message GIỮ NGUYÊN
            //     nguyên văn (hợp đồng ROLE-02) + code FARM_PARTNER_REQUIRED;
            //     FE đọc farm_partner_status (từ /authenticate) để hiện màn
            //     "Đang chờ duyệt" cho 'requested'.
            if (!$customer->isFarmPartner()) {
                if ($customer->farm_partner_status === 'suspended') {
                    return response()->json([
                        'error'   => true,
                        'message' => 'Farm của bạn đang tạm dừng, vui lòng liên hệ admin',
                        'code'    => 'FARM_SUSPENDED',
                    ], 403);
                }

                return response()->json([
                    'error'   => true,
                    'message' => 'Bạn không có quyền truy cập chức năng Farm Partner',
                    'code'    => 'FARM_PARTNER_REQUIRED',
                ], 403);
            }

            // Verify customer có farm đang active. Lookup theo customers.farm_id
            // (cover cả owner và staff). Trước task Farm Staff, middleware tìm
            // theo farms.owner_customer_id, nay đã chuyển sang farm_id để 1 farm
            // có thể có nhiều người thao tác (owner + staff). Xem migration
            // 2026_05_19_100000.
            //
            // KHÔNG dùng scopeActive() trực tiếp: scopeActive (is_active=true AND
            // approved_at NOT NULL) gộp "farm bị tắt" với "chưa có row / chưa
            // duyệt" thành cùng null → không phân biệt được message. Thay vào đó
            // tra row KHÔNG scope rồi branch (ROLE-05):
            //   - row tồn tại nhưng is_active=false (từng active rồi admin tắt)
            //     → "tạm dừng" + FARM_SUSPENDED (thống nhất với suspend partner).
            //   - không có row HOẶC approved_at=null (đang onboarding, chưa duyệt
            //     lần đầu) → "chưa được gán" + FARM_NOT_ASSIGNED.
            $farm = $customer->farm_id ? Farm::find($customer->farm_id) : null;

            if ($farm && $farm->is_active === false) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Farm của bạn đang tạm dừng, vui lòng liên hệ admin',
                    'code'    => 'FARM_SUSPENDED',
                ], 403);
            }

            if (!$farm || $farm->approved_at === null) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Tài khoản farm partner chưa được gán farm hoặc farm đã bị vô hiệu hoá',
                    'code'    => 'FARM_NOT_ASSIGNED',
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
