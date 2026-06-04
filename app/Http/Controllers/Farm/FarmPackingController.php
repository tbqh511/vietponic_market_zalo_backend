<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\OrderFarmAssignment;
use App\Models\ZaloOrder;
use App\Services\PackingService;
use App\Support\ContactMasker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FarmPackingController — thao tác đóng gói trên Mini App (Farm Partner).
 *
 * Mọi route dưới middleware `zalo.farm` (EnsureFarmPartner), đã đính sẵn
 * `farm` + `zalo_customer` vào request attributes. Mỗi action thao tác trên
 * phiếu (order, farm) của CHÍNH farm đang đăng nhập.
 *
 * Phân quyền:
 *   - assign: chỉ chủ farm (owner).
 *   - start-packing / confirm-packed: nhân viên được gán cho phiếu đó (hoặc
 *     owner — owner cũng có thể tự đóng).
 *   - detail: owner xem mọi phiếu của farm; staff chỉ xem phiếu được gán.
 *
 * Logic nghiệp vụ + ghi audit log nằm trong PackingService.
 */
class FarmPackingController extends Controller
{
    public function __construct(private PackingService $packing) {}

    /**
     * GET /farm/orders/{orderId} — chi tiết phần-của-farm của một đơn:
     * items của farm này + thông tin giao đã che + trạng thái phiếu.
     */
    public function show(Request $request, int $orderId): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $assignment = $this->findAssignment($farm, $orderId);
        if (! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn của farm này'], 404);
        }

        // Staff chỉ xem được phiếu gán cho mình.
        if ($customer->isFarmStaff() && (int) $assignment->assigned_customer_id !== (int) $customer->id) {
            return response()->json(['error' => true, 'message' => 'Bạn không được phân công đơn này'], 403);
        }

        $order = ZaloOrder::with('delivery')->find($orderId);
        if (! $order) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $items = DB::table('zalo_order_items')
            ->where('order_id', $orderId)
            ->where('farm_id', $farm->id)
            ->get(['id', 'product_id', 'name', 'quantity', 'price']);

        $delivery   = $order->delivery;
        $isPickup   = $delivery && $delivery->type === 'pickup';

        return response()->json([
            'error' => false,
            'data'  => [
                'order_id'          => (int) $order->id,
                'order_status'      => $order->status,
                'order_created_at'  => $order->created_at,
                'assignment_status' => $assignment->status,
                'assigned_customer_id' => $assignment->assigned_customer_id !== null
                    ? (int) $assignment->assigned_customer_id : null,
                'is_mine'           => (int) $assignment->assigned_customer_id === (int) $customer->id,
                'is_pickup'         => (bool) $isPickup,
                // Thông tin giao đã che server-side.
                'customer_name'     => $delivery?->name,
                'customer_phone'    => ContactMasker::maskPhone($delivery?->phone),
                'delivery_address'  => $isPickup
                    ? ($delivery?->station_name)
                    : ContactMasker::maskAddress($delivery?->address),
                'items' => $items->map(fn ($it) => [
                    'item_id'      => (int) $it->id,
                    'product_id'   => (int) $it->product_id,
                    'product_name' => (string) $it->name,
                    'quantity'     => (float) $it->quantity,
                    'price'        => (float) $it->price,
                ])->all(),
            ],
        ]);
    }

    /**
     * POST /farm/orders/{orderId}/start-packing — nhân viên được gán bắt đầu đóng.
     */
    public function startPacking(Request $request, int $orderId): JsonResponse
    {
        return $this->actAsAssignee($request, $orderId, function ($assignment, $customer) {
            return $this->packing->startPacking($assignment, $customer);
        });
    }

    /**
     * POST /farm/orders/{orderId}/confirm-packed — xác nhận đóng gói xong.
     * Khi mọi farm của đơn xác nhận xong → đơn tự sang 'delivering'.
     */
    public function confirmPacked(Request $request, int $orderId): JsonResponse
    {
        return $this->actAsAssignee($request, $orderId, function ($assignment, $customer) {
            return $this->packing->confirmPacked($assignment, $customer);
        });
    }

    /**
     * POST /farm/orders/{orderId}/claim — nhân viên TỰ NHẬN phiếu chưa ai nhận.
     * Khác assign (owner gán): người gọi tự nhận cho mình, chỉ nhận được phiếu
     * 'unassigned'. Owner cũng có thể tự nhận để tự đóng.
     */
    public function claim(Request $request, int $orderId): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $assignment = $this->findAssignment($farm, $orderId);
        if (! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn của farm này'], 404);
        }

        try {
            $assignment = $this->packing->claim($assignment, $customer);
        } catch (\DomainException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => $this->assignmentPayload($assignment, $customer)]);
    }

    /**
     * POST /farm/orders/{orderId}/confirm-order — CHỈ owner: xác nhận đơn
     * (pending → confirmed), bước trước đóng gói. Khớp nút "Xác nhận đơn".
     */
    public function confirmOrder(Request $request, int $orderId): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $owner */
        $owner = $request->attributes->get('zalo_customer');

        if (! $owner->isFarmOwner()) {
            return response()->json(['error' => true, 'message' => 'Chỉ chủ farm được xác nhận đơn'], 403);
        }

        // Đảm bảo farm thực sự có phần trong đơn này.
        if (! $this->findAssignment($farm, $orderId)) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn của farm này'], 404);
        }

        try {
            $order = $this->packing->confirmOrder($orderId, $farm->id, $owner);
        } catch (\DomainException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => ['order_id' => (int) $order->id, 'order_status' => $order->status]]);
    }

    /**
     * POST /farm/orders/{orderId}/handoff-ship — CHỈ owner: bàn giao vận chuyển
     * (preparing → delivering). Chỉ được khi mọi phần farm của đơn đã đóng xong.
     */
    public function handoffShipping(Request $request, int $orderId): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $owner */
        $owner = $request->attributes->get('zalo_customer');

        if (! $owner->isFarmOwner()) {
            return response()->json(['error' => true, 'message' => 'Chỉ chủ farm được bàn giao vận chuyển'], 403);
        }

        if (! $this->findAssignment($farm, $orderId)) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn của farm này'], 404);
        }

        try {
            $order = $this->packing->handoffShipping($orderId, $farm->id, $owner);
        } catch (\DomainException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => ['order_id' => (int) $order->id, 'order_status' => $order->status]]);
    }

    /**
     * POST /farm/orders/{orderId}/assign — CHỈ owner: gán/đổi packer cho phiếu
     * của farm mình. Body: { packer_customer_id }.
     */
    public function assign(Request $request, int $orderId): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $owner */
        $owner = $request->attributes->get('zalo_customer');

        if (! $owner->isFarmOwner()) {
            return response()->json(['error' => true, 'message' => 'Chỉ chủ farm được phân công đơn'], 403);
        }

        $data = $request->validate([
            'packer_customer_id' => 'required|integer|exists:customers,id',
        ]);

        $assignment = $this->findAssignment($farm, $orderId);
        if (! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn của farm này'], 404);
        }

        $packer = Customer::find($data['packer_customer_id']);
        if (! $packer) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy nhân viên'], 404);
        }

        try {
            $assignment = $this->packing->assign($assignment, $packer, $owner);
        } catch (\DomainException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => $this->assignmentPayload($assignment, $owner)]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Tìm phiếu (order, farm) — tạo lazily nếu farm có item trong đơn mà chưa
     * có phiếu (đồng bộ với ensureAssignmentsExist của incomingOrders).
     */
    private function findAssignment(Farm $farm, int $orderId): ?OrderFarmAssignment
    {
        $assignment = OrderFarmAssignment::where('order_id', $orderId)
            ->where('farm_id', $farm->id)
            ->first();

        if ($assignment) {
            return $assignment;
        }

        // Chỉ tạo nếu farm thực sự có item trong đơn (tránh tạo phiếu rác).
        $hasItems = DB::table('zalo_order_items')
            ->where('order_id', $orderId)
            ->where('farm_id', $farm->id)
            ->exists();

        if (! $hasItems) {
            return null;
        }

        return OrderFarmAssignment::create([
            'order_id' => $orderId,
            'farm_id'  => $farm->id,
            'status'   => OrderFarmAssignment::STATUS_UNASSIGNED,
        ]);
    }

    /**
     * Bọc các action yêu cầu người gọi là người được gán (hoặc owner). Verify
     * quyền rồi gọi $callback(assignment, customer).
     */
    private function actAsAssignee(Request $request, int $orderId, callable $callback): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $assignment = $this->findAssignment($farm, $orderId);
        if (! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn của farm này'], 404);
        }

        // Staff: chỉ thao tác phiếu gán cho mình. Owner: được phép thao tác mọi phiếu.
        $isAssignee = (int) $assignment->assigned_customer_id === (int) $customer->id;
        if (! $isAssignee && ! $customer->isFarmOwner()) {
            return response()->json(['error' => true, 'message' => 'Bạn không được phân công đơn này'], 403);
        }

        try {
            $assignment = $callback($assignment, $customer);
        } catch (\DomainException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => $this->assignmentPayload($assignment, $customer)]);
    }

    private function assignmentPayload(OrderFarmAssignment $assignment, Customer $viewer): array
    {
        return [
            'order_id'             => (int) $assignment->order_id,
            'assignment_status'    => $assignment->status,
            'assigned_customer_id' => $assignment->assigned_customer_id !== null
                ? (int) $assignment->assigned_customer_id : null,
            'is_mine'              => (int) $assignment->assigned_customer_id === (int) $viewer->id,
        ];
    }
}
