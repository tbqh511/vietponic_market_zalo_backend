<?php

namespace App\Support;

use App\Jobs\SendZaloNotification;

/**
 * Helper dispatch notification dùng chung cho controller / listener / service,
 * tránh lặp lời gọi SendZaloNotification::dispatch ở 4 điểm trigger.
 */
trait DispatchesOrderNotifications
{
    protected function dispatchOrderNotification(int $orderId, string $type, array $extra = []): void
    {
        SendZaloNotification::dispatch($orderId, $type, $extra);
    }
}
