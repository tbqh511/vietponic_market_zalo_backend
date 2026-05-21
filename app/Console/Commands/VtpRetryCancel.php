<?php

namespace App\Console\Commands;

use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Services\ViettelPostService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retry hủy đơn VTP cho các order đã cancel local nhưng VTP cancel chưa
 * xác nhận (vtp_status_code chưa rơi vào terminal cancel codes).
 *
 * Cách hoạt động:
 *  - Tìm các delivery có vtp_cancel_requested_at != null và vtp_status_code
 *    chưa nằm trong terminal cancel codes (101/105/107/201/503/504/505).
 *  - Skip nếu attempts >= MAX_ATTEMPTS (mặc định 5) — admin xử lý thủ công.
 *  - Skip nếu lần fail gần nhất < BACKOFF (mặc định 1 giờ) để tránh DDoS VTP.
 *  - Gọi ViettelPostService::cancelOrder() lại, cập nhật state tương ứng.
 *
 * Usage:
 *   php artisan vtp:retry-cancel               # retry tất cả đơn đang pending cancel
 *   php artisan vtp:retry-cancel 123           # retry một đơn cụ thể
 *   php artisan vtp:retry-cancel --max=3       # custom max attempts
 *   php artisan vtp:retry-cancel --dry-run     # liệt kê, không gọi VTP
 *
 * Schedule: chạy mỗi 30 phút trong Console/Kernel.php.
 */
class VtpRetryCancel extends Command
{
    private const TERMINAL_CANCEL_CODES = ['101', '105', '107', '201', '503', '504', '505'];
    private const DEFAULT_MAX_ATTEMPTS  = 5;
    private const BACKOFF_MINUTES       = 60;

    protected $signature = 'vtp:retry-cancel
        {order_id? : ID đơn hàng cụ thể cần retry (bỏ qua filter)}
        {--max=5 : Số lần thử tối đa trước khi dừng (default 5)}
        {--dry-run : Liệt kê đơn cần retry mà không gọi VTP}';

    protected $description = 'Retry hủy đơn VTP cho các đơn đã cancel local nhưng VTP cancel chưa xác nhận';

    public function handle(ViettelPostService $vtp): int
    {
        $maxAttempts = max(1, (int) $this->option('max'));
        $dryRun      = (bool) $this->option('dry-run');

        $query = ZaloDelivery::query()
            ->whereNotNull('vtp_cancel_requested_at')
            ->whereNotNull('vtp_order_number')
            ->whereNotIn('vtp_status_code', self::TERMINAL_CANCEL_CODES)
            ->where('vtp_cancel_attempts', '<', $maxAttempts);

        if ($id = $this->argument('order_id')) {
            $query->where('order_id', $id);
        } else {
            // Backoff: chỉ retry nếu lần fail gần nhất > BACKOFF_MINUTES.
            $query->where(function ($q) {
                $q->whereNull('vtp_cancel_failed_at')
                  ->orWhere('vtp_cancel_failed_at', '<=', Carbon::now()->subMinutes(self::BACKOFF_MINUTES));
            });
        }

        $deliveries = $query->with('order')->get();

        if ($deliveries->isEmpty()) {
            $this->info('Không có đơn VTP nào cần retry hủy.');
            return self::SUCCESS;
        }

        $this->info("Tìm thấy {$deliveries->count()} đơn cần retry hủy:");
        $okCount = 0;
        $failCount = 0;

        foreach ($deliveries as $delivery) {
            $order = $delivery->order;
            if (!$order) {
                $this->warn("  - Delivery #{$delivery->id}: không tìm thấy order, bỏ qua.");
                continue;
            }

            $line = sprintf(
                "  - Order #%d / VTP %s / attempts=%d / status=%s",
                $order->id,
                $delivery->vtp_order_number,
                $delivery->vtp_cancel_attempts,
                $delivery->vtp_status_code ?? '-'
            );

            if ($dryRun) {
                $this->line($line . ' [dry-run]');
                continue;
            }

            $this->line($line);
            $reason = (string) ($order->cancellation_reason ?? 'Retry cancel');
            if ($this->attemptCancel($vtp, $delivery, $reason)) {
                $okCount++;
                $this->info("    ✓ OK");
            } else {
                $failCount++;
                $this->error("    ✗ {$delivery->fresh()->vtp_cancel_last_error}");
            }
        }

        if (!$dryRun) {
            $this->info("Hoàn tất: {$okCount} OK, {$failCount} fail.");
        }
        return self::SUCCESS;
    }

    private function attemptCancel(ViettelPostService $vtp, ZaloDelivery $delivery, string $reason): bool
    {
        $delivery->vtp_cancel_attempts = (int) $delivery->vtp_cancel_attempts + 1;

        try {
            $vtp->cancelOrder(
                (string) $delivery->vtp_order_number,
                1,
                mb_substr("[retry] {$reason}", 0, 200)
            );
            $delivery->vtp_status_code       = '105';
            $delivery->vtp_status_name       = 'Đã huỷ';
            $delivery->vtp_status_at         = now();
            $delivery->vtp_cancel_failed_at  = null;
            $delivery->vtp_cancel_last_error = null;
            $delivery->save();
            Log::channel('viettelpost')->info('[vtp:retry-cancel] OK', [
                'order_id' => $delivery->order_id,
                'vtp'      => $delivery->vtp_order_number,
                'attempts' => $delivery->vtp_cancel_attempts,
            ]);
            return true;
        } catch (\Throwable $e) {
            $delivery->vtp_cancel_failed_at  = now();
            $delivery->vtp_cancel_last_error = mb_substr($e->getMessage(), 0, 500);
            $delivery->save();
            Log::channel('viettelpost')->error('[vtp:retry-cancel] FAIL', [
                'order_id' => $delivery->order_id,
                'vtp'      => $delivery->vtp_order_number,
                'attempts' => $delivery->vtp_cancel_attempts,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }
}
