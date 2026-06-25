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
 * FarmPackingController — thao tác đóng gói trên Mini App (MÔ HÌNH PACKAGE HUB).
 *
 * Mọi route dưới middleware `zalo.farm` (EnsureFarmPartner), đã đính sẵn
 * `farm` + `zalo_customer` vào request attributes.
 *
 * Khâu đóng gói tập trung tại Package Hub: chỉ farm là hub (is_packing_hub) mới
 * được THAO TÁC đơn (xác nhận / phân công / nhận / đóng / bàn giao). Hub đóng gói
 * TOÀN BỘ đơn — phiếu đóng gói là (order, hub), 1 phiếu/đơn, KHÔNG theo farm sở
 * hữu hàng. Farm thường gọi action ghi → 403 (chỉ xem chỉ-đọc qua incoming).
 *
 * Phân quyền trong hub:
 *   - confirm-order / handoff-ship / assign: chỉ chủ hub (owner).
 *   - claim / start-packing / confirm-packed: nhân viên được gán (hoặc owner).
 *   - detail: owner xem mọi phiếu; staff chỉ xem phiếu được gán.
 *
 * Logic nghiệp vụ + ghi audit log nằm trong PackingService.
 */
class FarmPackingController extends Controller
{
    public function __construct(private PackingService $packing) {}

    /**
     * Gác cổng: chỉ Package Hub được thao tác đơn. Trả JsonResponse 403 nếu farm
     * đang đăng nhập không phải hub; null nếu hợp lệ (cho phép tiếp tục).
     */
    private function requirePackingHub(Request $request): ?JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        if (! $farm || ! $farm->is_packing_hub) {
            return response()->json([
                'error'   => true,
                'message' => 'Chỉ bộ phận đóng gói Vietponics được xử lý đơn.',
            ], 403);
        }
        return null;
    }

    /**
     * GET /farm/orders/{orderId} — chi tiết đơn cho khâu đóng gói.
     *
     * Hub: xem TOÀN BỘ items của đơn (đóng gói cả đơn) + trạng thái phiếu hub.
     * Farm thường: chỉ xem chỉ-đọc phần hàng của mình (items farm_id = farm này).
     */
    public function show(Request $request, int $orderId): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $isHub = (bool) $farm->is_packing_hub;

        $assignment = $this->findHubAssignment($orderId);
        // Hub: bắt buộc có phiếu. Farm thường: chỉ cần đơn có hàng của mình.
        if ($isHub && ! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy phiếu đóng gói của đơn này'], 404);
        }

        // Packer/shipper chỉ xem được phiếu gán cho mình; owner/admin xem tất cả.
        if ($isHub && ! $customer->canManageFarm()
            && (int) optional($assignment)->assigned_customer_id !== (int) $customer->id) {
            return response()->json(['error' => true, 'message' => 'Bạn không được phân công đơn này'], 403);
        }

        $order = ZaloOrder::with('delivery')->find($orderId);
        if (! $order) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Hub thấy mọi item của đơn; farm thường chỉ phần hàng của mình.
        $itemsQuery = DB::table('zalo_order_items')->where('order_id', $orderId);
        if (! $isHub) {
            $itemsQuery->where('farm_id', $farm->id);
        }
        $items = $itemsQuery->get(['id', 'product_id', 'name', 'quantity', 'price']);

        // Farm thường không có phần trong đơn (không item nào) → 404.
        if (! $isHub && $items->isEmpty()) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn của farm này'], 404);
        }

        $delivery   = $order->delivery;
        $isPickup   = $delivery && $delivery->type === 'pickup';

        // Tên người đóng (nếu phiếu đã gán) — để màn chi tiết hiện "Đang đóng: …".
        $assignedName = $assignment && $assignment->assigned_customer_id !== null
            ? optional($assignment->assignedCustomer)->name
            : null;

        return response()->json([
            'error' => false,
            'data'  => [
                'order_id'          => (int) $order->id,
                'order_status'      => $order->status,
                'order_created_at'  => $order->created_at,
                'order_total'       => (float) $order->total,
                'assignment_status' => optional($assignment)->status
                    ?? OrderFarmAssignment::STATUS_UNASSIGNED,
                'assigned_customer_id' => optional($assignment)->assigned_customer_id !== null
                    ? (int) $assignment->assigned_customer_id : null,
                'assigned_customer_name' => $assignedName,
                'packing_started_at' => optional($assignment)->packing_started_at,
                'packed_at'          => optional($assignment)->packed_at,
                'is_mine'           => $isHub && $assignment
                    && (int) $assignment->assigned_customer_id === (int) $customer->id,
                // Farm thường = xem chỉ-đọc.
                'read_only'         => ! $isHub,
                'is_pickup'         => (bool) $isPickup,
                // Tên trạm cho đơn pickup (key riêng, độc lập delivery_address).
                'station_name'      => $isPickup ? ($delivery?->station_name) : null,
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
     * POST /farm/orders/{orderId}/start-packing — nhân viên hub được gán bắt đầu đóng.
     */
    public function startPacking(Request $request, int $orderId): JsonResponse
    {
        if ($deny = $this->requirePackingHub($request)) {
            return $deny;
        }
        return $this->actAsAssignee($request, $orderId, function ($assignment, $customer) {
            return $this->packing->startPacking($assignment, $customer);
        });
    }

    /**
     * POST /farm/orders/{orderId}/confirm-packed — xác nhận đóng gói xong phiếu hub.
     * KHÔNG tự đẩy đơn sang 'delivering' — chủ hub bấm "Bàn giao ship" riêng.
     */
    public function confirmPacked(Request $request, int $orderId): JsonResponse
    {
        if ($deny = $this->requirePackingHub($request)) {
            return $deny;
        }
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
        if ($deny = $this->requirePackingHub($request)) {
            return $deny;
        }
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $assignment = $this->findHubAssignment($orderId);
        if (! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy phiếu đóng gói của đơn này'], 404);
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
        if ($deny = $this->requirePackingHub($request)) {
            return $deny;
        }
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $owner */
        $owner = $request->attributes->get('zalo_customer');

        if (! $owner->canManageFarm()) {
            return response()->json(['error' => true, 'message' => 'Chỉ chủ hub hoặc quản lý được xác nhận đơn'], 403);
        }

        // Đảm bảo có phiếu hub cho đơn này (đơn có hàng).
        if (! $this->findHubAssignment($orderId)) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy phiếu đóng gói của đơn này'], 404);
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
        if ($deny = $this->requirePackingHub($request)) {
            return $deny;
        }
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var Customer $owner */
        $owner = $request->attributes->get('zalo_customer');

        if (! $owner->canManageFarm()) {
            return response()->json(['error' => true, 'message' => 'Chỉ chủ hub hoặc quản lý được bàn giao vận chuyển'], 403);
        }

        if (! $this->findHubAssignment($orderId)) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy phiếu đóng gói của đơn này'], 404);
        }

        try {
            $order = $this->packing->handoffShipping($orderId, $farm->id, $owner);
        } catch (\DomainException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => ['order_id' => (int) $order->id, 'order_status' => $order->status]]);
    }

    /**
     * POST /farm/orders/{orderId}/handoff-internal — CHỈ owner/admin: bàn giao
     * cho shipper nội bộ. Set delivery_method='internal' + gán shipper_customer_id.
     * Có thể gọi thay cho handoff-ship khi farm tự giao (không dùng VTP).
     * Body: { shipper_customer_id }
     */
    public function handoffInternal(Request $request, int $orderId): JsonResponse
    {
        if ($deny = $this->requirePackingHub($request)) {
            return $deny;
        }
        /** @var Customer $actor */
        $actor = $request->attributes->get('zalo_customer');

        if (! $actor->canManageFarm()) {
            return response()->json(['error' => true, 'message' => 'Chỉ chủ hub hoặc quản lý được bàn giao giao hàng nội bộ'], 403);
        }

        $data = $request->validate([
            'shipper_customer_id' => 'required|integer|exists:customers,id',
        ]);

        $order = ZaloOrder::find($orderId);
        if (! $order) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }
        if ($order->status !== 'preparing') {
            return response()->json(['error' => true, 'message' => "Đơn đang ở trạng thái '{$order->status}', không thể bàn giao"], 422);
        }

        $shipper = Customer::find($data['shipper_customer_id']);
        if (! $shipper || ! $shipper->isFarmShipper() && ! $shipper->canManageFarm()) {
            return response()->json(['error' => true, 'message' => 'Người được gán không phải shipper của farm'], 422);
        }

        DB::transaction(function () use ($order, $shipper) {
            $order->delivery_method      = 'internal';
            $order->shipper_customer_id  = $shipper->id;
            $order->status               = 'delivering';
            $order->save();
        });

        return response()->json([
            'error' => false,
            'data'  => [
                'order_id'            => (int) $order->id,
                'order_status'        => $order->status,
                'delivery_method'     => $order->delivery_method,
                'shipper_customer_id' => (int) $order->shipper_customer_id,
            ],
        ]);
    }

    /**
     * POST /farm/orders/{orderId}/assign — CHỈ chủ hub: gán/đổi packer (thành
     * viên hub) cho phiếu đóng gói của đơn. Body: { packer_customer_id }.
     */
    public function assign(Request $request, int $orderId): JsonResponse
    {
        if ($deny = $this->requirePackingHub($request)) {
            return $deny;
        }
        /** @var Customer $owner */
        $owner = $request->attributes->get('zalo_customer');

        if (! $owner->canManageFarm()) {
            return response()->json(['error' => true, 'message' => 'Chỉ chủ hub hoặc quản lý được phân công đơn'], 403);
        }

        $data = $request->validate([
            'packer_customer_id' => 'required|integer|exists:customers,id',
        ]);

        $assignment = $this->findHubAssignment($orderId);
        if (! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy phiếu đóng gói của đơn này'], 404);
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
     * Tìm phiếu đóng gói của đơn — LUÔN thuộc Package Hub (1 phiếu/đơn). Tạo
     * lazily nếu đơn có item mà chưa có phiếu hub (đồng bộ với
     * ensureAssignmentsExist của incomingOrders). Trả null nếu chưa cấu hình
     * hub hoặc đơn không có item nào.
     */
    private function findHubAssignment(int $orderId): ?OrderFarmAssignment
    {
        $hub = Farm::primaryPackingHub();
        if (! $hub) {
            return null;
        }

        $assignment = OrderFarmAssignment::where('order_id', $orderId)
            ->where('farm_id', $hub->id)
            ->first();

        if ($assignment) {
            return $assignment;
        }

        // Chỉ tạo nếu đơn thực sự có hàng (có item bất kỳ) — tránh tạo phiếu rác.
        $hasItems = DB::table('zalo_order_items')
            ->where('order_id', $orderId)
            ->exists();

        if (! $hasItems) {
            return null;
        }

        return OrderFarmAssignment::create([
            'order_id' => $orderId,
            'farm_id'  => $hub->id,
            'status'   => OrderFarmAssignment::STATUS_UNASSIGNED,
        ]);
    }

    /**
     * Bọc các action yêu cầu người gọi là người được gán (hoặc owner). Verify
     * quyền rồi gọi $callback(assignment, customer).
     */
    private function actAsAssignee(Request $request, int $orderId, callable $callback): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $assignment = $this->findHubAssignment($orderId);
        if (! $assignment) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy phiếu đóng gói của đơn này'], 404);
        }

        // Packer/shipper: chỉ thao tác phiếu gán cho mình. Owner/admin: mọi phiếu.
        $isAssignee = (int) $assignment->assigned_customer_id === (int) $customer->id;
        if (! $isAssignee && ! $customer->canManageFarm()) {
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
