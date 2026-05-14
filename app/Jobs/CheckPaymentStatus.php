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

    public int $tries = 3;
    public int $timeout = 30;

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
        $endpoint = 'https://payment-mini.zalo.me/api/transaction/get-status';

        // Phase A diagnostic — capture exact request shape so we can correlate -2101 với
        // payload thật. Không log secret hay full mac (chỉ prefix 8 ký tự).
        Log::info('CheckPaymentStatus: outbound', [
            'orderId' => $this->orderId,
            'checkoutSdkOrderId' => $this->checkoutSdkOrderId,
            'miniAppId' => $this->miniAppId,
            'miniAppIdType' => gettype($this->miniAppId),
            'miniAppIdLen' => strlen((string) $this->miniAppId),
            'attempt' => $this->attempt,
            'dataMac' => $dataMac,
            'macPrefix' => substr($mac, 0, 8),
            'secretLen' => strlen((string) $privateKey),
            'endpoint' => $endpoint,
        ]);

        try {
            $response = Http::timeout(10)->get($endpoint, [
                'orderId' => $this->checkoutSdkOrderId,
                'appId' => $this->miniAppId,
                'mac' => $mac,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CheckPaymentStatus: HTTP exception', [
                'orderId' => $this->orderId,
                'checkoutSdkOrderId' => $this->checkoutSdkOrderId,
                'attempt' => $this->attempt,
                'endpoint' => $endpoint,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            $response = null;
        }

        if ($response && $response->successful()) {
            $body = $response->json() ?? [];
            // Zalo trả { returnCode, returnMessage, data: {...} } — returnCode ở top-level.
            // Fallback đọc trong data[] để phòng trường hợp Zalo đổi shape.
            $returnCode = $body['returnCode'] ?? ($body['data']['returnCode'] ?? null);
            $returnMessage = $body['returnMessage'] ?? ($body['data']['returnMessage'] ?? null);

            if ($returnCode == 1) {
                $order->payment_status = 'success';
                $order->save();
                event(new \App\Events\OrderPaymentSucceeded($order->id));
            } elseif ($returnCode == -1) {
                $order->payment_status = 'failed';
                $order->save();
            } else {
                // Phase A — luôn log đầy đủ khi returnCode != 1/-1 (gồm -2101 MAC sai).
                // Trước đây chỉ log khi "không xác định" → bỏ sót case -2101 vì 200 OK.
                Log::warning('CheckPaymentStatus: returnCode không xác định', [
                    'orderId' => $this->orderId,
                    'checkoutSdkOrderId' => $this->checkoutSdkOrderId,
                    'attempt' => $this->attempt,
                    'returnCode' => $returnCode,
                    'returnMessage' => $returnMessage,
                    'response' => $body,
                ]);
            }
        } elseif ($response) {
            Log::warning('CheckPaymentStatus: HTTP get-status thất bại', [
                'orderId' => $this->orderId,
                'checkoutSdkOrderId' => $this->checkoutSdkOrderId,
                'attempt' => $this->attempt,
                'endpoint' => $endpoint,
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
