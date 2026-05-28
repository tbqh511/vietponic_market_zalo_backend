<?php

namespace App\Listeners;

use App\Events\OrderPaymentSucceeded;
use App\Jobs\SendZaloNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Bắn tin Zalo "thanh toán thành công" khi OrderPaymentSucceeded fire.
 *
 * ShouldQueue để webhook /notify trả response nhanh (giống CreateVtpOrderOnPayment).
 * Chỉ enqueue job SendZaloNotification (job đã có retry/backoff riêng) nên listener
 * giữ $tries = 1 để không retry chồng.
 */
class SendOrderNotification implements ShouldQueue
{
    public int $tries = 1;

    public function handle(OrderPaymentSucceeded $event): void
    {
        SendZaloNotification::dispatch($event->orderId, 'paid');
    }
}
