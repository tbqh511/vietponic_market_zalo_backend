<?php

namespace App\Console\Commands;

use App\Models\AffiliateCommission;
use App\Models\ZaloOrder;
use App\Services\RefundService;
use App\Services\StockService;
use App\Services\ViettelPostService;
use App\Services\VoucherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-cancel các đơn payment online (BANK/ZALOPAY/MOMO) bị kẹt ở pending/failed.
 *
 * Trigger conditions (chỉ áp dụng cho status='pending' và payment_method NOT LIKE 'COD%'):
 *  - payment_status='failed'  → cancel ngay (user thanh toán fail / không đủ tiền)
 *  - payment_status='pending' AND created_at < now() - 30 phút → cancel timeout
 *    (user đóng app giữa chừng, hoặc /notify không tới + poll không xác nhận)
 *
 * Side effects per order (theo đúng thứ tự cancelByCustomer):
 *  1. status='cancelled', cancelled_by='system', cancellation_reason
 *  2. StockService::releaseReservation()       — trả tồn kho
 *  3. VoucherService::release()                — trả voucher (nếu có)
 *  4. RefundService::processCancellationRefund() — sẽ set 'not_required'
 *     vì payment_status chưa 'success'
 *  5. Affiliate commission clawback (nếu có)
 *  6. Cancel VTP order (safety net — Phase 1 đã ngăn tạo VTP sớm cho online,
 *     nhưng nếu có legacy data hoặc race thì vẫn cancel)
 *
 * Idempotent: skip đơn đã 'cancelled'. Lock per-order trong transaction.
 *
 * Usage:
 *   php artisan orders:auto-cancel-stale                  # chạy thật
 *   php artisan orders:auto-cancel-stale --dry-run        # chỉ liệt kê
 *   php artisan orders:auto-cancel-stale --timeout=45     # custom timeout phút
 *   php artisan orders:auto-cancel-stale --limit=50       # giới hạn số đơn/lần
 *
 * Schedule: mỗi 5 phút trong Console/Kernel.php.
 */
class AutoCancelStaleOrders extends Command
{
    private const DEFAULT_TIMEOUT_MINUTES = 30;
    private const DEFAULT_LIMIT           = 100;

    protected $signature = 'orders:auto-cancel-stale
        {--timeout=30 : Số phút sau create_at sẽ coi pending là stale (default 30)}
        {--limit=100 : Giới hạn số đơn xử lý mỗi lần chạy (default 100)}
        {--dry-run : Liệt kê đơn sẽ cancel mà không thực hiện}';

    protected $description = 'Auto-cancel đơn online thanh toán failed hoặc pending quá ngưỡng — release stock + voucher + VTP';

    public function handle(
        StockService $stockService,
        VoucherService $voucherService,
        RefundService $refundService,
        ViettelPostService $vtpService,
    ): int {
        $timeout = max(1, (int) $this->option('timeout'));
        $limit   = max(1, (int) $this->option('limit'));
        $dryRun  = (bool) $this->option('dry-run');

        $threshold = now()->subMinutes($timeout);

        // Query: status='pending' (chưa được admin xử lý), không phải COD, và
        //   (payment_status='failed') OR (payment_status='pending' AND created_at < threshold)
        $query = ZaloOrder::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                // Loại COD/COD_SANDBOX (NOT LIKE không trigger được index khi prefix wildcard,
                // dùng whereNotIn cho explicit + dùng index)
                $q->whereNotNull('payment_method')
                  ->whereNotIn('payment_method', ['COD', 'COD_SANDBOX']);
            })
            ->where(function ($q) use ($threshold) {
                $q->where('payment_status', 'failed')
                  ->orWhere(function ($q2) use ($threshold) {
                      $q2->where('payment_status', 'pending')
                         ->where('created_at', '<', $threshold);
                  });
            })
            ->orderBy('id')
            ->limit($limit);

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->info('Không có đơn nào cần auto-cancel.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '[%s] Tìm thấy %d đơn cần auto-cancel (timeout=%d phút, limit=%d).',
            $dryRun ? 'DRY-RUN' : 'RUN',
            $orders->count(),
            $timeout,
            $limit,
        ));

        $cancelled = 0;
        $skipped   = 0;
        $errors    = 0;

        foreach ($orders as $orderSummary) {
            $reason = $orderSummary->payment_status === 'failed'
                ? 'Auto-cancel: thanh toán thất bại'
                : "Auto-cancel: thanh toán quá hạn ({$timeout} phút)";

            $this->line(sprintf(
                '  #%d  payment_method=%s  payment_status=%s  created_at=%s  → %s',
                $orderSummary->id,
                $orderSummary->payment_method,
                $orderSummary->payment_status,
                $orderSummary->created_at,
                $reason,
            ));

            if ($dryRun) {
                continue;
            }

            try {
                $result = $this->cancelOne(
                    $orderSummary->id,
                    $reason,
                    $stockService,
                    $voucherService,
                    $refundService,
                    $vtpService,
                );

                if ($result === 'cancelled') {
                    $cancelled++;
                } else {
                    $skipped++;
                    $this->warn("    └─ skipped: {$result}");
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->error("    └─ error: " . $e->getMessage());
                Log::error('AutoCancelStaleOrders: failed', [
                    'order_id'  => $orderSummary->id,
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            'Kết quả: cancelled=%d, skipped=%d, errors=%d',
            $cancelled,
            $skipped,
            $errors,
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Cancel 1 order trong transaction với lockForUpdate. Trả về 'cancelled'
     * nếu thành công, hoặc lý do skip (string) nếu không match điều kiện.
     */
    private function cancelOne(
        int $orderId,
        string $reason,
        StockService $stockService,
        VoucherService $voucherService,
        RefundService $refundService,
        ViettelPostService $vtpService,
    ): string {
        // Phase A: trong transaction — lock + double-check + update status.
        // Tách side effects ra ngoài transaction để không giữ lock khi gọi
        // VTP HTTP (latency cao).
        $order = DB::transaction(function () use ($orderId) {
            $order = ZaloOrder::where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                return ['skip' => 'not found'];
            }

            // Double-check: trạng thái có thể đã đổi giữa query và lock
            // (webhook /notify đến rất sớm, hoặc admin vừa xử lý).
            if ($order->status !== 'pending') {
                return ['skip' => "status changed to {$order->status}"];
            }
            $method = strtoupper((string) $order->payment_method);
            if (str_starts_with($method, 'COD')) {
                return ['skip' => 'is COD'];
            }
            if ($order->payment_status === 'success') {
                return ['skip' => 'payment succeeded (race with webhook)'];
            }

            $order->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancelled_by'        => 'system',
                'cancellation_reason' => mb_substr($reason, 0, 500),
            ]);

            return ['order' => $order];
        });

        if (isset($order['skip'])) {
            return $order['skip'];
        }

        /** @var ZaloOrder $orderModel */
        $orderModel = $order['order'];

        // Phase B: side effects — KHÔNG throw, chỉ log. Pattern giống cancelByCustomer.
        try {
            $stockService->releaseReservation($orderModel->id);
        } catch (\Throwable $e) {
            Log::error('AutoCancelStaleOrders: releaseReservation failed', [
                'order_id' => $orderModel->id,
                'message'  => $e->getMessage(),
            ]);
        }

        try {
            $voucherService->release($orderModel);
        } catch (\Throwable $e) {
            Log::error('AutoCancelStaleOrders: voucher release failed', [
                'order_id' => $orderModel->id,
                'message'  => $e->getMessage(),
            ]);
        }

        try {
            // payment_status không phải 'success' → RefundService sẽ set
            // refund_status='not_required'. Vẫn gọi để giữ invariant.
            $refundService->processCancellationRefund($orderModel, 'admin');
        } catch (\Throwable $e) {
            Log::error('AutoCancelStaleOrders: processCancellationRefund failed', [
                'order_id' => $orderModel->id,
                'message'  => $e->getMessage(),
            ]);
        }

        // Clawback affiliate commission (nếu Phase 1 chưa kịp release sớm
        // hoặc do listener khác đã ghi pending — an toàn vì where status IN
        // pending/confirmed only).
        try {
            $affected = AffiliateCommission::where('order_id', $orderModel->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->update([
                    'status'     => 'cancelled',
                    'notes'      => mb_substr($reason, 0, 500),
                    'updated_at' => now(),
                ]);
            if ($affected > 0) {
                Log::info('AutoCancelStaleOrders: clawback affiliate', [
                    'order_id' => $orderModel->id,
                    'affected' => $affected,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('AutoCancelStaleOrders: affiliate clawback failed', [
                'order_id' => $orderModel->id,
                'message'  => $e->getMessage(),
            ]);
        }

        // VTP cancel — safety net. Phase 1 đã ngăn tạo VTP cho đơn online ở
        // store(), nhưng có thể có legacy data trước migration, hoặc race
        // giữa listener và auto-cancel (rất hiếm vì listener chỉ chạy khi
        // payment success).
        $orderModel->loadMissing('delivery');
        if ($orderModel->delivery && !empty($orderModel->delivery->vtp_order_number)) {
            $delivery = $orderModel->delivery;
            $delivery->vtp_cancel_attempts = (int) $delivery->vtp_cancel_attempts + 1;
            if (empty($delivery->vtp_cancel_requested_at)) {
                $delivery->vtp_cancel_requested_at = now();
            }
            try {
                $vtpService->cancelOrder(
                    (string) $delivery->vtp_order_number,
                    1,
                    mb_substr($reason, 0, 200),
                );
                $delivery->vtp_status_code       = '105';
                $delivery->vtp_status_name       = 'Đã huỷ';
                $delivery->vtp_status_at         = now();
                $delivery->vtp_cancel_failed_at  = null;
                $delivery->vtp_cancel_last_error = null;
                $delivery->save();
                Log::channel('viettelpost')->info('[AutoCancel VTP] OK', [
                    'order_id'     => $orderModel->id,
                    'order_number' => $delivery->vtp_order_number,
                ]);
            } catch (\Throwable $e) {
                $delivery->vtp_cancel_failed_at  = now();
                $delivery->vtp_cancel_last_error = mb_substr($e->getMessage(), 0, 500);
                $delivery->save();
                // Không throw — vtp:retry-cancel cron sẽ retry.
                Log::channel('viettelpost')->error('[AutoCancel VTP] FAIL', [
                    'order_id'     => $orderModel->id,
                    'order_number' => $delivery->vtp_order_number,
                    'message'      => $e->getMessage(),
                ]);
            }
        }

        return 'cancelled';
    }
}
