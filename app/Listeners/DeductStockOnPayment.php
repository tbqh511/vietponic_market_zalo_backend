<?php

namespace App\Listeners;

use App\Events\OrderPaymentSucceeded;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;

class DeductStockOnPayment
{
    public function __construct(private StockService $stockService) {}

    public function handle(OrderPaymentSucceeded $event): void
    {
        try {
            $this->stockService->deductOnPayment($event->orderId);
        } catch (\Throwable $e) {
            Log::error('DeductStockOnPayment failed', [
                'orderId'   => $event->orderId,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ]);
        }
    }
}
