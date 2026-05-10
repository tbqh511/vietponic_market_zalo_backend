<?php

namespace App\Jobs;

use App\Models\ZaloOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckPaymentStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;
    protected $checkoutSdkOrderId;
    protected $miniAppId;
    protected $attempt;

    public function __construct($orderId, $checkoutSdkOrderId, $miniAppId, $attempt = 0)
    {
        $this->orderId = $orderId;
        $this->checkoutSdkOrderId = $checkoutSdkOrderId;
        $this->miniAppId = $miniAppId;
        $this->attempt = $attempt;
    }

    public function handle()
    {
        $order = ZaloOrder::find($this->orderId);
        if (!$order || $order->payment_status !== 'pending') {
            return;
        }

        $privateKey = env('ZALO_CHECK_OUT_SECRET');
        $dataMac = "appId={$this->miniAppId}&orderId={$this->checkoutSdkOrderId}";
        $mac = hash_hmac('sha256', $dataMac, $privateKey);

        $response = Http::get('https://payment-mini.zalo.me/api/transaction/get-status', [
            'orderId' => $this->checkoutSdkOrderId,
            'appId' => $this->miniAppId,
            'mac' => $mac,
        ]);

        if ($response->successful()) {
            $body = $response->json() ?? [];
            // Zalo trả { returnCode, returnMessage, data: {...} } — returnCode ở top-level.
            // Fallback đọc trong data[] để phòng trường hợp Zalo đổi shape.
            $returnCode = $body['returnCode'] ?? ($body['data']['returnCode'] ?? null);

            if ($returnCode == 1) {
                $order->payment_status = 'success';
                $order->save();
            } elseif ($returnCode == -1) {
                $order->payment_status = 'failed';
                $order->save();
            } else {
                Log::warning('CheckPaymentStatus: returnCode không xác định', [
                    'orderId' => $this->orderId,
                    'checkoutSdkOrderId' => $this->checkoutSdkOrderId,
                    'response' => $body,
                ]);
            }
        } else {
            Log::warning('CheckPaymentStatus: HTTP get-status thất bại', [
                'orderId' => $this->orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        $order->refresh();
        if ($order->payment_status === 'pending' && $this->attempt < 2) {
            // Backoff: 30s (lần đầu) → 2min → 10min, sau đó dừng.
            $nextDelay = $this->attempt === 0 ? 120 : 600;
            self::dispatch($this->orderId, $this->checkoutSdkOrderId, $this->miniAppId, $this->attempt + 1)
                ->delay(now()->addSeconds($nextDelay));
        }
    }
}
