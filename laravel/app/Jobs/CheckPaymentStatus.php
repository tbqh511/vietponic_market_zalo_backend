<?php

namespace App\Jobs;

use App\Models\ZaloOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CheckPaymentStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;
    protected $checkoutSdkOrderId;
    protected $miniAppId;

    public function __construct($orderId, $checkoutSdkOrderId, $miniAppId)
    {
        $this->orderId = $orderId;
        $this->checkoutSdkOrderId = $checkoutSdkOrderId;
        $this->miniAppId = $miniAppId;
    }

    public function handle()
    {
        $order = ZaloOrder::find($this->orderId);
        if (!$order || $order->payment_status !== 'pending') {
            return;
        }

        $privateKey = env('ZALO_CHECK_OUT_SECRET');
        $dataMac = "appId={$this->miniAppId}&orderId={$this->checkoutSdkOrderId}&privateKey={$privateKey}";
        $mac = hash('sha256', $dataMac);

        $response = Http::get('https://payment-mini.zalo.me/api/transaction/get-status', [
            'orderId' => $this->checkoutSdkOrderId,
            'appId' => $this->miniAppId,
            'mac' => $mac,
        ]);

        if ($response->successful()) {
            $data = $response->json()['data'];
            if ($data['returnCode'] == 1) {
                $order->payment_status = 'success';
            } elseif ($data['returnCode'] == -1) {
                $order->payment_status = 'failed';
            }
            $order->save();
        }
    }
}
