<?php

namespace App\Http\Controllers\Farm;

use App\Events\OrderDelivered;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ZaloOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FarmShipperController — workflow giao hàng nội bộ trên Mini App.
 *
 * Dành cho đơn có delivery_method='internal' (không qua ViettelPost).
 * Mọi route dưới middleware `zalo.farm` (EnsureFarmPartner).
 *
 * Phân quyền:
 *   - index / pickup / deliver: shipper được gán HOẶC owner/admin (canManageFarm).
 *   - Chỉ owner/admin mới có thể gán shipper (qua handoff-ship của FarmPackingController).
 *
 * Status flow: delivering → (pickup) → out_for_delivery → (deliver) → delivered.
 */
class FarmShipperController extends Controller
{
    /**
     * GET /farm/shipments — danh sách đơn giao nội bộ của shipper đang đăng nhập.
     * owner/admin xem tất cả đơn internal của farm; shipper chỉ xem của mình.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $query = ZaloOrder::where('delivery_method', 'internal')
            ->whereIn('status', ['delivering', 'out_for_delivery'])
            ->orderByDesc('id');

        if (! $customer->canManageFarm()) {
            $query->where('shipper_customer_id', $customer->id);
        }

        $orders = $query->get(['id', 'status', 'total', 'created_at', 'shipper_customer_id', 'picked_up_at']);

        return response()->json([
            'error' => false,
            'data'  => $orders->map(fn ($o) => [
                'order_id'             => (int) $o->id,
                'status'               => $o->status,
                'total'                => (float) $o->total,
                'created_at'           => $o->created_at,
                'shipper_customer_id'  => $o->shipper_customer_id,
                'picked_up_at'         => $o->picked_up_at,
            ])->all(),
        ]);
    }

    /**
     * POST /farm/shipments/{orderId}/pickup — shipper xác nhận đã nhận hàng.
     * delivering → out_for_delivery.
     */
    public function pickup(Request $request, int $orderId): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $order = ZaloOrder::find($orderId);
        if (! $order) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        if ($order->delivery_method !== 'internal') {
            return response()->json(['error' => true, 'message' => 'Đơn này không phải giao hàng nội bộ'], 422);
        }

        // Chỉ shipper được gán hoặc owner/admin mới được pickup.
        if (! $customer->canManageFarm() && (int) $order->shipper_customer_id !== (int) $customer->id) {
            return response()->json(['error' => true, 'message' => 'Bạn không được phân công giao đơn này'], 403);
        }

        if ($order->status !== 'delivering') {
            return response()->json(['error' => true, 'message' => "Đơn đang ở trạng thái '{$order->status}', không thể nhận"], 422);
        }

        DB::transaction(function () use ($order, $customer) {
            $order->status      = 'out_for_delivery';
            $order->picked_up_at = now();
            // Gán shipper nếu chưa có (owner/admin pickup trực tiếp).
            if (! $order->shipper_customer_id) {
                $order->shipper_customer_id = $customer->id;
            }
            $order->save();
        });

        return response()->json([
            'error' => false,
            'data'  => ['order_id' => (int) $order->id, 'status' => $order->status],
        ]);
    }

    /**
     * POST /farm/shipments/{orderId}/deliver — shipper xác nhận đã giao thành công.
     * out_for_delivery → delivered. Fire OrderDelivered để ghi hoa hồng CTV.
     */
    public function deliver(Request $request, int $orderId): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $order = ZaloOrder::find($orderId);
        if (! $order) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        if ($order->delivery_method !== 'internal') {
            return response()->json(['error' => true, 'message' => 'Đơn này không phải giao hàng nội bộ'], 422);
        }

        if (! $customer->canManageFarm() && (int) $order->shipper_customer_id !== (int) $customer->id) {
            return response()->json(['error' => true, 'message' => 'Bạn không được phân công giao đơn này'], 403);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['error' => true, 'message' => "Đơn đang ở trạng thái '{$order->status}', không thể xác nhận đã giao"], 422);
        }

        DB::transaction(function () use ($order) {
            $order->status       = 'delivered';
            $order->delivered_at = now();
            $order->save();
        });

        // Fire để ghi hoa hồng CTV (cùng pattern với VTP webhook và admin updateStatus).
        OrderDelivered::dispatch($order->id);

        return response()->json([
            'error' => false,
            'data'  => ['order_id' => (int) $order->id, 'status' => $order->status],
        ]);
    }
}
