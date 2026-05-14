<?php

namespace App\Listeners;

use App\Services\StockService;
use Illuminate\Support\Facades\Log;

class ReleaseStockOnCancellation
{
    public function __construct(private StockService $stockService) {}

    public function handle(int $orderId): void
    {
        try {
            $this->stockService->releaseReservation($orderId);
        } catch (\Throwable $e) {
            Log::error('ReleaseStockOnCancellation failed', [
                'orderId'   => $orderId,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ]);
        }
    }
}
