<?php

namespace App\Services;

use App\Models\ZaloOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP client cho ZaloPay Mini App refund.
 *
 * Pattern signing giống CheckPaymentStatus: HMAC-SHA256 với ZALO_CHECK_OUT_SECRET
 * trên chuỗi "k1=v1&k2=v2..." (ksort).
 *
 * Endpoint Mini App: payment-mini.zalo.me/api/transaction/refund
 * (cùng host với /get-status đã dùng). Nếu Zalo không expose endpoint refund
 * trên kênh Mini App, requestRefund() trả về false → RefundService fallback
 * sang pending_manual để kế toán xử lý.
 */
class ZaloPayRefundClient
{
    private const REFUND_ENDPOINT = 'https://payment-mini.zalo.me/api/transaction/refund';
    private const QUERY_ENDPOINT  = 'https://payment-mini.zalo.me/api/transaction/query-refund';

    /**
     * Gửi yêu cầu hoàn tiền tới ZaloPay.
     *
     * Lưu m_refund_id vào order TRƯỚC khi gọi API để guard double-refund khi retry.
     *
     * @return array{ok:bool, status:string, providerId?:string, message?:string}
     */
    public function requestRefund(ZaloOrder $order, string $description = ''): array
    {
        if (!$order->checkout_sdk_order_id) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'Order missing checkout_sdk_order_id'];
        }

        $secret = env('ZALO_CHECK_OUT_SECRET');
        if (!$secret) {
            Log::error('ZaloPayRefundClient: missing ZALO_CHECK_OUT_SECRET');
            return ['ok' => false, 'status' => 'failed', 'message' => 'Server config error'];
        }

        $mRefundId = $order->refund_transaction_id
            ?: 'refund_' . $order->id . '_' . Str::random(8) . '_' . time();
        $amount = (int) round((float) $order->total);
        $timestamp = (int) round(microtime(true) * 1000);
        $miniAppId = env('ZALO_APP_ID', config('services.zalo.app_id', ''));
        $desc = $description !== '' ? $description : "Hoàn tiền đơn hàng #{$order->id}";

        $params = [
            'appId'       => (string) $miniAppId,
            'mRefundId'   => $mRefundId,
            'orderId'     => $order->checkout_sdk_order_id,
            'amount'      => (string) $amount,
            'description' => $desc,
            'timestamp'   => (string) $timestamp,
        ];
        $mac = $this->signParams($params, $secret);

        // Persist mRefundId trước khi gọi để retry idempotent
        $order->update([
            'refund_transaction_id' => $mRefundId,
            'refund_amount'         => $amount,
            'refund_method'         => $order->payment_method,
        ]);

        Log::info('ZaloPayRefundClient: outbound refund', [
            'order_id'    => $order->id,
            'mRefundId'   => $mRefundId,
            'amount'      => $amount,
            'endpoint'    => self::REFUND_ENDPOINT,
            'macPrefix'   => substr($mac, 0, 8),
        ]);

        try {
            $response = Http::timeout(15)->asForm()->post(self::REFUND_ENDPOINT, array_merge($params, ['mac' => $mac]));
        } catch (\Throwable $e) {
            Log::warning('ZaloPayRefundClient: HTTP exception', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);
            return ['ok' => false, 'status' => 'failed', 'message' => $e->getMessage()];
        }

        if (!$response->successful()) {
            Log::warning('ZaloPayRefundClient: HTTP failure', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return ['ok' => false, 'status' => 'failed', 'message' => 'HTTP ' . $response->status()];
        }

        $body = $response->json() ?? [];
        $returnCode = $body['returnCode'] ?? ($body['data']['returnCode'] ?? null);
        $providerId = $body['refundId'] ?? ($body['data']['refundId'] ?? null);

        // returnCode mapping (theo convention ZaloPay):
        //   1  → đã hoàn tất
        //   2  → thất bại
        //   3  → đang xử lý (poll qua CheckRefundStatus)
        //   khác → log + fallback failed
        if ($returnCode == 1) {
            return ['ok' => true, 'status' => 'refunded', 'providerId' => (string) $providerId];
        }
        if ($returnCode == 3) {
            return ['ok' => true, 'status' => 'processing', 'providerId' => (string) $providerId];
        }
        if ($returnCode == 2) {
            return ['ok' => false, 'status' => 'failed', 'message' => $body['returnMessage'] ?? 'Refund rejected'];
        }

        Log::warning('ZaloPayRefundClient: unknown returnCode', [
            'order_id'   => $order->id,
            'returnCode' => $returnCode,
            'body'       => $body,
        ]);
        return ['ok' => false, 'status' => 'failed', 'message' => 'Unknown returnCode: ' . $returnCode];
    }

    /**
     * Query refund status (dùng cho CheckRefundStatus job).
     *
     * @return array{ok:bool, status:string, message?:string}
     */
    public function queryRefund(ZaloOrder $order): array
    {
        if (!$order->refund_transaction_id) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'No refund_transaction_id'];
        }

        $secret = env('ZALO_CHECK_OUT_SECRET');
        $miniAppId = env('ZALO_APP_ID', config('services.zalo.app_id', ''));
        $timestamp = (int) round(microtime(true) * 1000);

        $params = [
            'appId'     => (string) $miniAppId,
            'mRefundId' => $order->refund_transaction_id,
            'timestamp' => (string) $timestamp,
        ];
        $mac = $this->signParams($params, $secret);

        try {
            $response = Http::timeout(10)->asForm()->post(self::QUERY_ENDPOINT, array_merge($params, ['mac' => $mac]));
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'failed', 'message' => $e->getMessage()];
        }

        if (!$response->successful()) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'HTTP ' . $response->status()];
        }

        $body = $response->json() ?? [];
        $returnCode = $body['returnCode'] ?? ($body['data']['returnCode'] ?? null);

        if ($returnCode == 1) return ['ok' => true, 'status' => 'refunded'];
        if ($returnCode == 3) return ['ok' => true, 'status' => 'processing'];
        if ($returnCode == 2) return ['ok' => false, 'status' => 'failed', 'message' => $body['returnMessage'] ?? 'Refund failed'];

        return ['ok' => false, 'status' => 'failed', 'message' => 'Unknown returnCode: ' . $returnCode];
    }

    private function signParams(array $params, string $secret): string
    {
        ksort($params);
        $raw = collect($params)
            ->map(fn ($v, $k) => $k . '=' . (is_array($v) ? json_encode($v) : $v))
            ->implode('&');
        return hash_hmac('sha256', $raw, $secret);
    }
}
