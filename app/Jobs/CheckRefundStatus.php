<?php

namespace App\Jobs;

use App\Models\ZaloOrder;
use App\Services\ZaloPayRefundClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Poll trạng thái refund từ ZaloPay cho đơn ở state 'processing'.
 *
 * Pattern giống CheckPaymentStatus: backoff 60s → 5min → 30min, max 3 lần.
 * Nếu hết tries mà vẫn processing → fallback pending_manual để kế toán xem.
 */
class CheckRefundStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(private int $orderId, private int $attempt = 0) {}

    public function handle(ZaloPayRefundClient $client): void
    {
        $order = ZaloOrder::find($this->orderId);
        if (!$order || $order->refund_status !== 'processing') {
            return;
        }

        $result = $client->queryRefund($order);

        if ($result['status'] === 'refunded') {
            $order->update([
                'refund_status' => 'refunded',
                'refunded_at'   => now(),
            ]);
            return;
        }

        if ($result['status'] === 'failed') {
            Log::warning('CheckRefundStatus: refund failed', [
                'order_id' => $order->id,
                'message'  => $result['message'] ?? 'unknown',
            ]);
            $order->update([
                'refund_status' => 'failed',
                'refund_note'   => 'ZaloPay báo refund thất bại: ' . ($result['message'] ?? 'unknown') . '. Cần kế toán xử lý thủ công.',
            ]);
            return;
        }

        // Vẫn processing → re-dispatch nếu còn tries
        if ($this->attempt < 2) {
            $nextDelay = $this->attempt === 0 ? 300 : 1800; // 5min → 30min
            self::dispatch($this->orderId, $this->attempt + 1)->delay(now()->addSeconds($nextDelay));
            return;
        }

        // Hết tries vẫn processing → fallback manual
        Log::warning('CheckRefundStatus: still processing after max attempts, falling back to manual', [
            'order_id' => $order->id,
        ]);
        $order->update([
            'refund_status' => 'pending_manual',
            'refund_note'   => 'ZaloPay refund vẫn processing sau 3 lần check — chờ kế toán xử lý thủ công.',
        ]);
    }
}
