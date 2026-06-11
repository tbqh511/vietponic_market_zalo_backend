<?php

namespace App\Jobs;

use App\Events\OrderPaymentSucceeded;
use App\Models\ZaloOrder;
use App\Services\StockService;
use App\Services\VoucherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CancelUnpaidOrder (ORDER-06) — tự huỷ đơn ONLINE chưa thanh toán sau timeout
 * và HOÀN KHO (releaseReservation). Dispatch từ ZaloApiController::checkout cho
 * mọi đơn online (kể cả đơn khách đóng cổng trước khi gọi /link).
 *
 * An toàn:
 *   - Best-effort poll Zalo get-status TRƯỚC khi huỷ (nếu có checkout_sdk_order_id
 *     + app id): nếu đơn THỰC SỰ đã trả (returnCode==1) → set 'success', KHÔNG huỷ.
 *   - lockForUpdate + re-check trong transaction → chống race với /notify chạy xen.
 *   - notifySDK + CheckPaymentStatus đều có guard status='cancelled' → đơn đã huỷ
 *     không bị hồi sinh thành paid.
 *
 * KHÔNG đụng: đơn COD, đơn đã success/failed, đơn không còn ở status='pending'.
 */
class CancelUnpaidOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    protected $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    /** Số phút chờ trước khi tự huỷ đơn online chưa trả (PO chốt: ~15–20′). */
    public static function timeoutMinutes(): int
    {
        return max(1, (int) env('ZALO_UNPAID_TIMEOUT_MINUTES', 20));
    }

    public function handle(): void
    {
        // Resolve service qua container (không dùng method-injection) để có thể gọi
        // thẳng (new CancelUnpaidOrder($id))->handle() trong test — đồng nhất với
        // CheckPaymentStatus.
        $stockService = app(StockService::class);
        $voucherService = app(VoucherService::class);

        // Đọc nhanh (không lock) để loại các đơn rõ ràng không cần đụng.
        $order = ZaloOrder::find($this->orderId);
        if (!$order) {
            return;
        }
        if (str_starts_with(strtoupper((string) $order->payment_method), 'COD')) {
            return; // COD không qua cổng online, không auto-cancel.
        }
        if ($order->status !== 'pending' || $order->payment_status !== 'pending') {
            return; // đã success/failed/cancelled hoặc đã được xử lý.
        }

        // Best-effort: hỏi Zalo lần cuối phòng đơn đã trả nhưng webhook trễ.
        if ($this->isAlreadyPaidAtZalo($order)) {
            $this->markPaidIfStillPending();
            return;
        }

        // Huỷ trong transaction có khoá + re-check (chống race với /notify).
        $cancelled = false;
        DB::transaction(function () use (&$cancelled) {
            $locked = ZaloOrder::where('id', $this->orderId)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }
            if (str_starts_with(strtoupper((string) $locked->payment_method), 'COD')) {
                return;
            }
            if ($locked->status !== 'pending' || $locked->payment_status !== 'pending') {
                return; // /notify đã commit success/failed xen giữa lúc poll.
            }

            $locked->update([
                'status'              => 'cancelled',
                'payment_status'      => 'failed',
                'cancelled_at'        => now(),
                'cancelled_by'        => 'system',
                'cancellation_reason' => 'Tự động huỷ: quá hạn thanh toán (chưa thanh toán trong '
                                       . self::timeoutMinutes() . ' phút)',
                'refund_status'       => 'not_required',
            ]);
            $cancelled = true;
        });

        if (!$cancelled) {
            return;
        }

        // Sau commit (ngoài lock): hoàn kho + nhả voucher (mỗi cái best-effort).
        try {
            $stockService->releaseReservation($this->orderId);
        } catch (\Throwable $e) {
            Log::error('CancelUnpaidOrder: releaseReservation thất bại', [
                'order_id' => $this->orderId,
                'message'  => $e->getMessage(),
            ]);
        }
        try {
            $fresh = ZaloOrder::find($this->orderId);
            if ($fresh) {
                $voucherService->release($fresh);
            }
        } catch (\Throwable $e) {
            Log::error('CancelUnpaidOrder: voucher release thất bại', [
                'order_id' => $this->orderId,
                'message'  => $e->getMessage(),
            ]);
        }

        Log::info('CancelUnpaidOrder: đơn online quá hạn đã tự huỷ + hoàn kho', [
            'order_id'        => $this->orderId,
            'timeout_minutes' => self::timeoutMinutes(),
        ]);
    }

    /**
     * Poll Zalo get-status (best-effort). Trả true CHỈ khi Zalo xác nhận đã trả
     * (returnCode==1). Mọi trường hợp khác (thiếu app id, không có sdk order id,
     * lỗi HTTP, MAC sai, returnCode khác) → false → đi tiếp nhánh huỷ.
     */
    private function isAlreadyPaidAtZalo(ZaloOrder $order): bool
    {
        $miniAppId = config('services.zalo.mini_app_id') ?: config('services.zalo.app_id');
        if (empty($order->checkout_sdk_order_id) || empty($miniAppId)) {
            return false;
        }

        $privateKey = env('ZALO_CHECK_OUT_SECRET');
        $dataMac = "appId={$miniAppId}&orderId={$order->checkout_sdk_order_id}";
        $mac = hash_hmac('sha256', $dataMac, $privateKey);

        try {
            $response = Http::timeout(10)->get('https://payment-mini.zalo.me/api/transaction/get-status', [
                'orderId' => $order->checkout_sdk_order_id,
                'appId'   => $miniAppId,
                'mac'     => $mac,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CancelUnpaidOrder: poll get-status lỗi HTTP — coi như chưa trả', [
                'order_id' => $this->orderId,
                'message'  => $e->getMessage(),
            ]);
            return false;
        }

        if (!$response || !$response->successful()) {
            return false;
        }

        $body = $response->json() ?? [];
        $returnCode = $body['returnCode'] ?? ($body['data']['returnCode'] ?? null);

        return (int) $returnCode === 1;
    }

    /**
     * Đơn đã trả theo poll → set 'success' (idempotent, có khoá + re-check) và
     * fire OrderPaymentSucceeded để tạo VTP / ghi commission như luồng /notify.
     */
    private function markPaidIfStillPending(): void
    {
        $saved = false;
        DB::transaction(function () use (&$saved) {
            $locked = ZaloOrder::where('id', $this->orderId)->lockForUpdate()->first();
            if ($locked && $locked->status !== 'cancelled' && $locked->payment_status === 'pending') {
                $locked->update(['payment_status' => 'success']);
                $saved = true;
            }
        });

        if ($saved) {
            event(new OrderPaymentSucceeded($this->orderId));
            Log::info('CancelUnpaidOrder: poll xác nhận ĐÃ trả — đánh dấu success, không huỷ', [
                'order_id' => $this->orderId,
            ]);
        }
    }
}
