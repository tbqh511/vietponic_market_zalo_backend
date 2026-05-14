<?php

namespace App\Listeners;

use App\Events\OrderPaymentSucceeded;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloOrder;
use Illuminate\Support\Facades\Log;

class RecordAffiliateCommission
{
    public function handle(OrderPaymentSucceeded $event): void
    {
        $order = ZaloOrder::find($event->orderId);
        if (!$order || !$order->customer_id) {
            return;
        }

        $referred = Customer::find($order->customer_id);
        if (!$referred || !$referred->referred_by_customer_id) {
            return;
        }

        // Self-attribution guard
        if ((int) $referred->referred_by_customer_id === (int) $referred->id) {
            return;
        }

        $referrer = Customer::find($referred->referred_by_customer_id);
        if (!$referrer || $referrer->affiliate_status !== 'approved') {
            return;
        }

        $orderTotal = (float) ($order->total ?? 0);
        if ($orderTotal <= 0) {
            return;
        }

        $rate = self::resolveCommissionRate();
        if ($rate <= 0) {
            return;
        }

        $commissionAmount = (int) round($orderTotal * $rate / 100);

        try {
            AffiliateCommission::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'referrer_customer_id' => $referrer->id,
                    'referred_customer_id' => $referred->id,
                    'order_total' => $orderTotal,
                    'commission_rate' => $rate,
                    'commission_amount' => $commissionAmount,
                    'status' => 'confirmed',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('RecordAffiliateCommission failed', [
                'orderId' => $order->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function resolveCommissionRate(): float
    {
        $row = Setting::where('type', 'affiliate_commission_rate')->first();
        if (!$row || $row->data === null || $row->data === '') {
            return 5.0;
        }
        return (float) $row->data;
    }
}
