<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Probe ZaloPay refund/query-refund endpoint with fake data.
 *
 * KHÔNG đụng đơn thật, KHÔNG ghi DB. Mục đích duy nhất: trả lời câu hỏi
 * "endpoint payment-mini.zalo.me/api/transaction/refund có tồn tại không?"
 * trước khi tin tưởng ZaloPayRefundClient sẽ chạy được trên production.
 *
 * Output phân loại:
 *   - OK_REJECTED  → endpoint tồn tại, trả về returnCode 2 (refund không tồn tại) — như mong đợi
 *   - OK_OTHER     → endpoint tồn tại, trả returnCode khác (xem chi tiết để hiểu)
 *   - NOT_FOUND    → endpoint KHÔNG tồn tại (404) — refund flow sẽ luôn fallback pending_manual
 *   - AUTH_ERROR   → secret/appId sai, sửa env rồi probe lại
 *   - NETWORK      → không kết nối được (firewall/DNS)
 */
class ProbeZaloPayRefund extends Command
{
    protected $signature = 'zalopay:probe-refund
        {--query : Probe /query-refund thay vì /refund}';

    protected $description = 'Smoke test ZaloPay Mini App refund endpoint with fake data (no DB writes)';

    private const REFUND_ENDPOINT = 'https://payment-mini.zalo.me/api/transaction/refund';
    private const QUERY_ENDPOINT  = 'https://payment-mini.zalo.me/api/transaction/query-refund';

    public function handle(): int
    {
        $secret = env('ZALO_CHECK_OUT_SECRET');
        $appId  = env('ZALO_APP_ID', config('services.zalo.app_id', ''));

        if (!$secret) {
            $this->error('ZALO_CHECK_OUT_SECRET chưa được set trong .env');
            return self::FAILURE;
        }
        if (!$appId) {
            $this->error('ZALO_APP_ID chưa được set trong .env');
            return self::FAILURE;
        }

        $useQuery = (bool) $this->option('query');
        $endpoint = $useQuery ? self::QUERY_ENDPOINT : self::REFUND_ENDPOINT;
        $mRefundId = 'probe_' . time() . '_' . Str::random(6);
        $timestamp = (int) round(microtime(true) * 1000);

        if ($useQuery) {
            $params = [
                'appId'     => (string) $appId,
                'mRefundId' => $mRefundId,
                'timestamp' => (string) $timestamp,
            ];
        } else {
            $params = [
                'appId'       => (string) $appId,
                'mRefundId'   => $mRefundId,
                'orderId'     => 'fake_order_' . $timestamp,
                'amount'      => '1000',
                'description' => 'Probe (do not process)',
                'timestamp'   => (string) $timestamp,
            ];
        }

        ksort($params);
        $raw = collect($params)
            ->map(fn ($v, $k) => $k . '=' . (is_array($v) ? json_encode($v) : $v))
            ->implode('&');
        $mac = hash_hmac('sha256', $raw, $secret);

        $this->info('─── ZaloPay Refund Endpoint Probe ───');
        $this->line("Endpoint:  {$endpoint}");
        $this->line("appId:     {$appId}");
        $this->line("mRefundId: {$mRefundId} (fake)");
        $this->line("mac:       " . substr($mac, 0, 16) . '...');
        $this->line('');

        try {
            $response = Http::timeout(15)->asForm()->post($endpoint, array_merge($params, ['mac' => $mac]));
        } catch (\Throwable $e) {
            $this->error('NETWORK error: ' . $e->getMessage());
            $this->line('→ Không kết nối được tới payment-mini.zalo.me. Kiểm tra DNS/firewall.');
            return self::FAILURE;
        }

        $status = $response->status();
        $body = $response->json() ?? [];
        $returnCode = $body['returnCode'] ?? ($body['data']['returnCode'] ?? null);
        $returnMessage = $body['returnMessage'] ?? ($body['data']['returnMessage'] ?? null);

        $this->line("HTTP status:    {$status}");
        $this->line('Response body:  ' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->line('');

        // Classify
        if ($status === 404) {
            $this->error('❌ NOT_FOUND — endpoint không tồn tại trên Mini App channel.');
            $this->warn('→ ZaloPayRefundClient::requestRefund() sẽ luôn fail trên production.');
            $this->warn('→ Toàn bộ luồng refund ZaloPay sẽ rơi vào pending_manual (đã có fallback).');
            $this->warn('→ Action: liên hệ ZaloPay support để xin endpoint refund đúng cho Mini App,');
            $this->warn('  hoặc chấp nhận manual-only và update docs.');
            return self::FAILURE;
        }

        if ($status >= 500) {
            $this->error("❌ SERVER_ERROR ({$status}) — endpoint trả 5xx, có thể tạm thời down hoặc payload sai format.");
            $this->warn('→ Thử lại sau vài phút. Nếu vẫn 5xx → liên hệ ZaloPay support.');
            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->warn("⚠️  HTTP {$status} — không phải 2xx nhưng cũng không phải 404/5xx.");
            $this->warn('→ Xem body để phán đoán. Có thể auth issue.');
            return self::FAILURE;
        }

        // 2xx — phân loại theo returnCode
        if ($returnCode === null) {
            $this->warn('⚠️  OK_OTHER — HTTP 2xx nhưng không có returnCode trong response.');
            $this->warn('→ Shape response khác convention. Cần update parser trong ZaloPayRefundClient.');
            return self::FAILURE;
        }

        // MAC sai / appId sai → thường trả mã âm như -2101, -2102
        if ($returnCode < 0) {
            $this->error("❌ AUTH_ERROR — returnCode={$returnCode}, message=\"{$returnMessage}\"");
            $this->warn('→ Endpoint TỒN TẠI nhưng auth fail (sai secret/appId/mac algorithm).');
            $this->warn("→ Action: confirm ZALO_CHECK_OUT_SECRET đúng với app ID {$appId}.");
            return self::FAILURE;
        }

        // returnCode 2 = refund không tồn tại → đúng như mong đợi với fake mRefundId
        if ($returnCode == 2) {
            $this->info("✅ OK_REJECTED — returnCode=2 (refund rejected/not found), message=\"{$returnMessage}\"");
            $this->info('→ Endpoint TỒN TẠI và parse được MAC. Refund flow sẽ hoạt động trên đơn thật.');
            return self::SUCCESS;
        }

        if ($returnCode == 1) {
            $this->warn("⚠️  UNEXPECTED — returnCode=1 (success) với fake mRefundId!");
            $this->warn('→ Endpoint trả success cho request giả → kiểm tra lại, có thể đang ở mock mode.');
            return self::FAILURE;
        }

        if ($returnCode == 3) {
            $this->warn("⚠️  UNEXPECTED — returnCode=3 (processing) với fake mRefundId.");
            $this->warn('→ Endpoint tồn tại nhưng response không như tài liệu.');
            return self::SUCCESS;
        }

        $this->warn("⚠️  OK_OTHER — returnCode={$returnCode} (không trong [1,2,3]), message=\"{$returnMessage}\"");
        $this->warn('→ Endpoint tồn tại nhưng returnCode không xác định.');
        $this->warn('→ Cần update ZaloPayRefundClient để handle case này.');
        return self::SUCCESS;
    }
}
