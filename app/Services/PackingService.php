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
 * PackingService — điều phối khâu đóng gói đơn cho cả Mini App (farm) và admin
 * web, gom logic vào một chỗ để 2 entry point không lệch dữ liệu.
 *
 * Mỗi cặp (order, farm) là một phiếu OrderFarmAssignment. Lifecycle:
 *   unassigned → assigned → packing → packed.
 *
 * Khi TẤT CẢ phiếu của một order đã 'packed', service đẩy order
 * preparing → delivering (bàn giao vận chuyển). KHÔNG nhân bản state-machine
 * guard của ZaloApiController::updateStatus — chỉ chuyển status + bắn thông
 * báo (giống đúng những gì admin web làm khi set 'delivering'). delivered_at
 * vẫn chỉ set ở bước delivered, không bị đụng ở đây.
 *
 * Mọi thao tác đều ghi OrderPackingLog để truy vết sự cố.
 *
 * @phpstan-type Actor Customer|User|null
 */
class PackingService
{
    use DispatchesOrderNotifications;

    /**
     * Gán (hoặc đổi) packer cho phiếu (order, farm). Packer phải thuộc đúng
     * farm này. $actor là người thực hiện gán (admin User hoặc chủ farm Customer).
     *
     * @param  Customer|User|null  $actor
     */
    public function assign(OrderFarmAssignment $assignment, Customer $packer, $actor): OrderFarmAssignment
    {
        if ((int) $packer->farm_id !== (int) $assignment->farm_id) {
            throw new \DomainException('Nhân viên được chọn không thuộc farm của đơn này.');
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
     * Xác nhận đã đóng gói xong phiếu này. Khi TẤT CẢ phiếu của order đã packed
     * → đẩy order sang 'delivering'.
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

            // Mọi phiếu của đơn đã đóng xong? → bàn giao vận chuyển (delivering).
            $remaining = OrderFarmAssignment::where('order_id', $assignment->order_id)
                ->where('status', '!=', OrderFarmAssignment::STATUS_PACKED)
                ->count();

            if ($remaining === 0) {
                $this->advanceOrderTo($assignment->order_id, 'delivering', $actor, $assignment->farm_id);
            }

            return $assignment;
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
