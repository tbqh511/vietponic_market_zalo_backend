<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\OrderFarmAssignment;
use App\Models\OrderPackingLog;
use App\Models\User;
use App\Models\ZaloOrder;
use App\Support\DispatchesOrderNotifications;
use Illuminate\Support\Facades\DB;

/**
 * PackingService — điều phối khâu đóng gói đơn cho cả Mini App và admin web,
 * gom logic vào một chỗ để 2 entry point không lệch dữ liệu.
 *
 * MÔ HÌNH PACKAGE HUB: khâu đóng gói tập trung tại Package Hub
 * (farms.is_packing_hub). Mỗi đơn có ĐÚNG 1 phiếu OrderFarmAssignment thuộc hub
 * (order, hub) — KHÔNG còn 1 phiếu/farm sở hữu hàng. Hub đóng gói toàn bộ đơn.
 * Lifecycle: unassigned → assigned → packing → packed.
 *
 * Nhân viên hub (staff) có thể TỰ NHẬN (claim) phiếu chưa ai nhận; chủ hub gán
 * trực tiếp. Khi phiếu hub đã 'packed', đơn KHÔNG tự sang delivering — chủ hub
 * bấm "Bàn giao ship" (handoffShipping) để chuyển preparing → delivering. Tách
 * bước này theo wireframe để owner kiểm soát thời điểm gọi vận chuyển. KHÔNG
 * nhân bản state-machine guard của ZaloApiController::updateStatus — chỉ chuyển
 * status + bắn thông báo (giống admin web). delivered_at vẫn chỉ set ở delivered.
 *
 * Mọi thao tác đều ghi OrderPackingLog để truy vết sự cố.
 *
 * @phpstan-type Actor Customer|User|null
 */
class PackingService
{
    use DispatchesOrderNotifications;

    /**
     * Gán (hoặc đổi) packer cho phiếu đóng gói (order, hub). Packer phải là
     * thành viên của HUB (assignment.farm_id = hub). $actor là người thực hiện
     * gán (admin User hoặc chủ hub Customer).
     *
     * @param  Customer|User|null  $actor
     */
    public function assign(OrderFarmAssignment $assignment, Customer $packer, $actor): OrderFarmAssignment
    {
        if ((int) $packer->farm_id !== (int) $assignment->farm_id) {
            throw new \DomainException('Nhân viên được chọn không thuộc bộ phận đóng gói (hub).');
        }
        if (! $packer->isFarmPartner()) {
            throw new \DomainException('Người được gán không phải thành viên farm hợp lệ.');
        }

        return DB::transaction(function () use ($assignment, $packer, $actor) {
            $assignment = OrderFarmAssignment::lockForUpdate()->findOrFail($assignment->id);

            // Không cho gán lại nếu phiếu đã đóng xong — tránh ghi đè lịch sử.
            if ($assignment->status === OrderFarmAssignment::STATUS_PACKED) {
                throw new \DomainException('Phiếu đã đóng gói xong — không thể đổi người gán.');
            }

            $wasAssigned = ! is_null($assignment->assigned_customer_id);
            $previousPackerId = $assignment->assigned_customer_id;

            $assignment->assigned_customer_id    = $packer->id;
            $assignment->assigned_by_user_id     = $actor instanceof User ? $actor->id : null;
            $assignment->assigned_by_customer_id = $actor instanceof Customer ? $actor->id : null;
            $assignment->assigned_at             = now();
            // Giữ trạng thái 'packing' nếu đang đóng dở mà chỉ đổi người; còn lại → assigned.
            if ($assignment->status === OrderFarmAssignment::STATUS_UNASSIGNED) {
                $assignment->status = OrderFarmAssignment::STATUS_ASSIGNED;
            }
            $assignment->save();

            OrderPackingLog::record(
                $assignment->order_id,
                $assignment->farm_id,
                $actor,
                $wasAssigned ? OrderPackingLog::ACTION_REASSIGNED : OrderPackingLog::ACTION_ASSIGNED,
                null,
                null,
                [
                    'packer_customer_id'   => $packer->id,
                    'packer_name'          => $packer->name,
                    'previous_packer_id'   => $previousPackerId,
                ]
            );

            return $assignment;
        });
    }

    /**
     * Nhân viên TỰ NHẬN một phiếu chưa ai nhận (self-claim). Người nhận phải
     * thuộc đúng farm của phiếu. Khác assign() ở chỗ actor == packer và chỉ cho
     * nhận phiếu đang 'unassigned' (không cướp phiếu người khác đang đóng).
     */
    public function claim(OrderFarmAssignment $assignment, Customer $packer): OrderFarmAssignment
    {
        if ((int) $packer->farm_id !== (int) $assignment->farm_id) {
            throw new \DomainException('Bạn không thuộc farm của đơn này.');
        }

        return DB::transaction(function () use ($assignment, $packer) {
            $assignment = OrderFarmAssignment::lockForUpdate()->findOrFail($assignment->id);

            // Đã có người nhận (kể cả chính mình) → không cho nhận lại. FE chặn
            // trước nhưng vẫn guard ở đây tránh race 2 nhân viên cùng bấm.
            if (! is_null($assignment->assigned_customer_id)) {
                if ((int) $assignment->assigned_customer_id === (int) $packer->id) {
                    return $assignment; // mình đã nhận rồi — idempotent
                }
                throw new \DomainException('Đơn đã có người nhận đóng gói.');
            }

            $assignment->assigned_customer_id    = $packer->id;
            $assignment->assigned_by_customer_id = $packer->id; // tự nhận
            $assignment->assigned_at             = now();
            $assignment->status                  = OrderFarmAssignment::STATUS_ASSIGNED;
            $assignment->save();

            OrderPackingLog::record(
                $assignment->order_id,
                $assignment->farm_id,
                $packer,
                OrderPackingLog::ACTION_CLAIMED,
                null,
                null,
                ['packer_customer_id' => $packer->id, 'packer_name' => $packer->name]
            );

            return $assignment;
        });
    }

    /**
     * Gỡ packer khỏi phiếu (về unassigned). Chỉ owner/admin dùng.
     *
     * @param  Customer|User|null  $actor
     */
    public function unassign(OrderFarmAssignment $assignment, $actor): OrderFarmAssignment
    {
        return DB::transaction(function () use ($assignment, $actor) {
            $assignment = OrderFarmAssignment::lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->status === OrderFarmAssignment::STATUS_PACKED) {
                throw new \DomainException('Phiếu đã đóng gói xong — không thể gỡ.');
            }

            $previousPackerId = $assignment->assigned_customer_id;

            $assignment->assigned_customer_id = null;
            $assignment->status               = OrderFarmAssignment::STATUS_UNASSIGNED;
            $assignment->assigned_at          = null;
            $assignment->packing_started_at   = null;
            $assignment->save();

            OrderPackingLog::record(
                $assignment->order_id,
                $assignment->farm_id,
                $actor,
                OrderPackingLog::ACTION_UNASSIGNED,
                null,
                null,
                ['previous_packer_id' => $previousPackerId]
            );

            return $assignment;
        });
    }

    /**
     * Nhân viên bắt đầu đóng gói phiếu được gán cho mình. Đảm bảo order ở
     * trạng thái ít nhất 'preparing' (đang chuẩn bị).
     *
     * @param  Customer|User|null  $actor
     */
    public function startPacking(OrderFarmAssignment $assignment, $actor): OrderFarmAssignment
    {
        return DB::transaction(function () use ($assignment, $actor) {
            $assignment = OrderFarmAssignment::lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->status === OrderFarmAssignment::STATUS_PACKED) {
                return $assignment; // đã xong — idempotent
            }
            if (is_null($assignment->assigned_customer_id)) {
                throw new \DomainException('Phiếu chưa được gán cho nhân viên nào.');
            }

            $assignment->status             = OrderFarmAssignment::STATUS_PACKING;
            $assignment->packing_started_at = $assignment->packing_started_at ?? now();
            $assignment->save();

            // Nâng order lên 'preparing' nếu đang ở pending/confirmed — báo hiệu
            // đơn đã vào khâu chuẩn bị. Không hạ trạng thái nếu đã cao hơn.
            $this->advanceOrderTo($assignment->order_id, 'preparing', $actor, $assignment->farm_id);

            OrderPackingLog::record(
                $assignment->order_id,
                $assignment->farm_id,
                $actor,
                OrderPackingLog::ACTION_PACKING_STARTED
            );

            return $assignment;
        });
    }

    /**
     * Xác nhận đã đóng gói xong phiếu này (status → packed). KHÔNG tự đẩy đơn
     * sang 'delivering' — chủ farm chủ động bấm "Bàn giao ship" (handoffShipping)
     * theo wireframe. Đây chỉ đánh dấu phần-của-farm đã gói xong.
     *
     * @param  Customer|User|null  $actor
     */
    public function confirmPacked(OrderFarmAssignment $assignment, $actor): OrderFarmAssignment
    {
        return DB::transaction(function () use ($assignment, $actor) {
            $assignment = OrderFarmAssignment::lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->status === OrderFarmAssignment::STATUS_PACKED) {
                return $assignment; // idempotent
            }
            if (is_null($assignment->assigned_customer_id)) {
                throw new \DomainException('Phiếu chưa được gán cho nhân viên nào.');
            }

            $assignment->status             = OrderFarmAssignment::STATUS_PACKED;
            $assignment->packed_at          = now();
            $assignment->packing_started_at = $assignment->packing_started_at ?? now();
            $assignment->save();

            OrderPackingLog::record(
                $assignment->order_id,
                $assignment->farm_id,
                $actor,
                OrderPackingLog::ACTION_PACKED
            );

            return $assignment;
        });
    }

    /**
     * Chủ farm/Admin xác nhận đơn (pending → confirmed) — bước trước đóng gói,
     * khớp nút "Xác nhận đơn" trên wireframe (#112). Chỉ tiến từ 'pending';
     * idempotent nếu đã ≥ confirmed.
     *
     * @param  Customer|User|null  $actor
     */
    public function confirmOrder(int $orderId, ?int $farmId, $actor): ZaloOrder
    {
        return DB::transaction(function () use ($orderId, $farmId, $actor) {
            $order = ZaloOrder::lockForUpdate()->findOrFail($orderId);

            if ($order->status === 'pending') {
                $previous = $order->status;
                $order->update(['status' => 'confirmed']);

                $this->dispatchOrderNotification($order->id, 'status_changed', ['status' => 'confirmed']);

                OrderPackingLog::record(
                    $order->id,
                    $farmId,
                    $actor,
                    OrderPackingLog::ACTION_ORDER_CONFIRMED,
                    $previous,
                    'confirmed'
                );
            }

            return $order;
        });
    }

    /**
     * Chủ hub/Admin "Bàn giao ship": đẩy đơn preparing → delivering, khớp nút
     * trên wireframe (#105). Chỉ cho phép khi phiếu hub của đơn đã 'packed'
     * (đơn đã gói xong). Idempotent nếu đơn đã ≥ delivering.
     *
     * @param  Customer|User|null  $actor
     */
    public function handoffShipping(int $orderId, ?int $farmId, $actor): ZaloOrder
    {
        return DB::transaction(function () use ($orderId, $farmId, $actor) {
            $order = ZaloOrder::lockForUpdate()->findOrFail($orderId);

            // Đã giao/đang giao/huỷ → không làm gì (idempotent với delivering+).
            if (in_array($order->status, ['delivering', 'delivered', 'cancelled'], true)) {
                return $order;
            }

            // Chặn bàn giao khi phiếu đóng gói của đơn chưa 'packed'. Mô hình hub
            // chỉ có 1 phiếu/đơn, nhưng query này vẫn an toàn nếu (legacy) còn
            // nhiều phiếu — yêu cầu mọi phiếu của đơn đã packed.
            $remaining = OrderFarmAssignment::where('order_id', $orderId)
                ->where('status', '!=', OrderFarmAssignment::STATUS_PACKED)
                ->count();
            if ($remaining > 0) {
                throw new \DomainException('Đơn chưa đóng gói xong — chưa thể bàn giao vận chuyển.');
            }

            // advanceOrderTo tự ghi log status_changed + bắn thông báo.
            $this->advanceOrderTo($orderId, 'delivering', $actor, $farmId);

            OrderPackingLog::record(
                $orderId,
                $farmId,
                $actor,
                OrderPackingLog::ACTION_HANDED_OFF,
                null,
                'delivering'
            );

            return $order->refresh();
        });
    }

    /**
     * Chuyển order tới $target ('preparing' | 'delivering') nếu đó là bước tiến
     * (không lùi). Chỉ áp dụng cho đơn còn đang xử lý — bỏ qua nếu đã
     * delivered/cancelled. Bắn thông báo + ghi log khi status thực sự đổi.
     *
     * Thứ tự rank để so sánh tiến/lùi.
     *
     * @param  Customer|User|null  $actor
     */
    protected function advanceOrderTo(int $orderId, string $target, $actor, ?int $farmId): void
    {
        $rank = [
            'pending'    => 0,
            'confirmed'  => 1,
            'preparing'  => 2,
            'delivering' => 3,
            'delivered'  => 4,
        ];

        $order = ZaloOrder::lockForUpdate()->find($orderId);
        if (! $order) {
            return;
        }

        // Đơn đã huỷ hoặc đã giao → không can thiệp.
        if ($order->status === 'cancelled' || $order->status === 'delivered') {
            return;
        }

        $current = $rank[$order->status] ?? -1;
        $next    = $rank[$target] ?? -1;

        // Chỉ tiến, không lùi (và không tự đặt 'delivered' ở đây).
        if ($next <= $current || $target === 'delivered') {
            return;
        }

        $previousStatus = $order->status;
        $order->update(['status' => $target]);

        $this->dispatchOrderNotification($order->id, 'status_changed', ['status' => $target]);

        OrderPackingLog::record(
            $order->id,
            $farmId,
            $actor,
            OrderPackingLog::ACTION_STATUS_CHANGED,
            $previousStatus,
            $target
        );
    }
}
