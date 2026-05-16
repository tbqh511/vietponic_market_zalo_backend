<?php

namespace App\Services;

use App\Jobs\CheckRefundStatus;
use App\Models\ZaloOrder;
use Illuminate\Support\Facades\Log;

/**
 * Điều phối refund theo payment method khi đơn bị hủy.
 *
 *  COD / chưa thanh toán  → refund_status = not_required
 *  ZALOPAY (đã trả)        → gọi ZaloPayRefundClient + poll
 *  MOMO / BANK (đã trả)   → pending_manual cho kế toán xử lý
 */
class RefundService
{
    public function __construct(private ZaloPayRefundClient $zaloPayClient) {}

    /**
     * @param 'customer'|'admin' $cancelledBy
     */
    public function processCancellationRefund(ZaloOrder $order, string $cancelledBy): void
    {
        // Idempotent: nếu đã có refund_status được set (≠ null) thì không làm lại
        if ($order->refund_status !== null) {
            return;
        }

        $method = strtoupper((string) $order->payment_method);

        // 1. COD hoặc chưa thanh toán thành công → không cần hoàn tiền
        $isCod = str_starts_with($method, 'COD');
        $paid  = $order->payment_status === 'success';

        if ($isCod || !$paid) {
            $order->update(['refund_status' => 'not_required']);
            return;
        }

        $note = $cancelledBy === 'customer'
            ? 'Khách hủy đơn — chờ kế toán xử lý hoàn tiền'
            : 'Admin hủy đơn — chờ kế toán xử lý hoàn tiền';

        // 2. ZaloPay → call API
        if (str_starts_with($method, 'ZALOPAY')) {
            $result = $this->zaloPayClient->requestRefund($order);

            if ($result['ok'] && $result['status'] === 'refunded') {
                $order->update([
                    'refund_status'      => 'refunded',
                    'refund_provider_id' => $result['providerId'] ?? null,
                    'refunded_at'        => now(),
                ]);
                return;
            }

            if ($result['ok'] && $result['status'] === 'processing') {
                $order->update([
                    'refund_status'      => 'processing',
                    'refund_provider_id' => $result['providerId'] ?? null,
                ]);
                CheckRefundStatus::dispatch($order->id)->delay(now()->addSeconds(60));
                return;
            }

            // Refund API thất bại → fallback sang pending_manual để kế toán xử lý
            Log::warning('RefundService: ZaloPay refund failed, falling back to manual', [
                'order_id' => $order->id,
                'message'  => $result['message'] ?? 'unknown',
            ]);
            $order->update([
                'refund_status' => 'pending_manual',
                'refund_note'   => 'ZaloPay refund API thất bại: ' . ($result['message'] ?? 'unknown') . '. Chờ kế toán xử lý.',
            ]);
            return;
        }

        // 3. MoMo / Bank → manual queue
        if (str_starts_with($method, 'MOMO') || str_starts_with($method, 'BANK')) {
            $order->update([
                'refund_status' => 'pending_manual',
                'refund_amount' => $order->total,
                'refund_method' => $order->payment_method,
                'refund_note'   => $note,
            ]);
            return;
        }

        // 4. Phương thức không xác định → manual để an toàn
        Log::warning('RefundService: unknown payment_method', [
            'order_id' => $order->id,
            'method'   => $method,
        ]);
        $order->update([
            'refund_status' => 'pending_manual',
            'refund_amount' => $order->total,
            'refund_method' => $order->payment_method,
            'refund_note'   => 'Phương thức thanh toán không xác định — kế toán kiểm tra thủ công',
        ]);
    }

    /**
     * Kế toán xác nhận đã hoàn tiền thủ công xong.
     */
    public function confirmManualRefund(ZaloOrder $order, ?string $adminNote = null): void
    {
        if ($order->refund_status !== 'pending_manual') {
            return;
        }
        $order->update([
            'refund_status' => 'refunded',
            'refunded_at'   => now(),
            'refund_note'   => $adminNote
                ? ($order->refund_note ? $order->refund_note . "\n— " . $adminNote : $adminNote)
                : ($order->refund_note ?? 'Đã hoàn tiền thủ công'),
        ]);
    }
}
