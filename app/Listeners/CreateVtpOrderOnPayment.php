<?php

namespace App\Listeners;

use App\Events\OrderPaymentSucceeded;
use App\Models\ZaloOrder;
use App\Services\VtpOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Tạo đơn VTP sau khi payment được xác nhận thành công (BANK/ZALOPAY/MOMO).
 *
 * Đơn COD đã tạo VTP ngay trong ZaloApiController::store() — listener này skip
 * để tránh duplicate (vẫn có guard !empty(vtp_order_number) trong service làm
 * safety net).
 *
 * ShouldQueue để webhook /notify trả response nhanh, không block bởi VTP HTTP call.
 */
class CreateVtpOrderOnPayment implements ShouldQueue
{
    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(private VtpOrderService $vtpService) {}

    public function handle(OrderPaymentSucceeded $event): void
    {
        $order = ZaloOrder::with('delivery')->find($event->orderId);
        if (!$order) {
            Log::channel('viettelpost')->warning('[CreateVtpOrderOnPayment] order not found', [
                'order_id' => $event->orderId,
            ]);
            return;
        }

        // Skip COD: VTP đã tạo trong store(). Guard payment_method để chắc chắn.
        $method = strtoupper((string) $order->payment_method);
        if (str_starts_with($method, 'COD')) {
            return;
        }

        // Skip nếu không phải shipping (pickup không cần VTP)
        if (!$order->delivery || $order->delivery->type !== 'shipping') {
            return;
        }

        // Idempotent guard — listener có thể fire nhiều lần (webhook retry, poll
        // job + webhook đồng thời). VtpOrderService::dispatchOrderToVtp() cũng
        // có guard nội bộ throw RuntimeException nếu vtp_order_number đã tồn tại.
        if (!empty($order->delivery->vtp_order_number)) {
            return;
        }

        try {
            $result = $this->vtpService->dispatchOrderToVtp($order);
            Log::channel('viettelpost')->info('[CreateVtpOrderOnPayment] OK', [
                'order_id'     => $order->id,
                'order_number' => $result['ORDER_NUMBER'] ?? null,
                'payment_method' => $method,
            ]);
        } catch (\Throwable $e) {
            // KHÔNG throw: payment đã success, rollback không khả thi. Log để
            // admin retry qua artisan vtp:retry-create.
            Log::channel('viettelpost')->error('[CreateVtpOrderOnPayment] FAIL', [
                'order_id'       => $order->id,
                'payment_method' => $method,
                'exception'      => get_class($e),
                'message'        => $e->getMessage(),
            ]);
        }
    }
}
