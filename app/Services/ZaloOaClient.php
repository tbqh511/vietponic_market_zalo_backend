<?php

namespace App\Services;

use App\Models\ZaloOaToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper gọi Zalo OA / ZNS OpenAPI.
 *
 * Hai kênh gửi tin:
 *   - sendOaMessage(): tin giao dịch OA theo user_id (= customers.firebase_id),
 *     miễn phí, chỉ gửi được khi khách đã follow OA.
 *   - sendZns(): ZNS theo SĐT, dùng template đã duyệt, có phí.
 *
 * Token lifecycle: access_token ~25h, refresh_token đổi sau MỖI lần refresh nên
 * lưu bền ở bảng zalo_oa_tokens (single-row). getAccessToken() tự refresh khi còn
 * <5 phút, có Cache::lock chống refresh đồng thời.
 *
 * Mọi method gửi tin trả ['ok' => bool, 'error' => ?int, 'message' => ?string].
 */
class ZaloOaClient
{
    private const OAUTH_URL    = 'https://oauth.zaloapp.com/v4/oa/access_token';
    private const ZNS_URL      = 'https://business.openapi.zalo.me/message/template';
    private const REFRESH_LOCK = 'zalo_oa_token_refresh';

    /** Refresh khi access_token còn dưới ngưỡng này (giây). */
    private const REFRESH_THRESHOLD = 300;

    private string $apiBaseUrl;

    public function __construct()
    {
        $this->apiBaseUrl = rtrim((string) config('services.zalo.api_base_url', 'https://openapi.zalo.me'), '/');
    }

    /**
     * Trả về access_token còn hạn, tự refresh nếu cần.
     *
     * @throws \RuntimeException nếu chưa seed token hoặc refresh thất bại.
     */
    public function getAccessToken(): string
    {
        $token = ZaloOaToken::current();

        if ($this->isFresh($token)) {
            return $token->access_token;
        }

        // Chống nhiều worker refresh đồng thời (refresh_token chỉ dùng được 1 lần).
        $lock = Cache::lock(self::REFRESH_LOCK, 15);
        if (!$lock->block(10)) {
            // Không lấy được lock trong 10s — đọc lại token (worker khác có thể đã refresh).
            $token->refresh();
            if ($this->isFresh($token)) {
                return $token->access_token;
            }
            throw new \RuntimeException('Zalo OA: không lấy được lock refresh token');
        }

        try {
            // Re-check sau khi giữ lock: worker khác có thể vừa refresh xong.
            $token->refresh();
            if ($this->isFresh($token)) {
                return $token->access_token;
            }

            return $this->refreshAccessToken($token);
        } finally {
            $lock->release();
        }
    }

    private function isFresh(ZaloOaToken $token): bool
    {
        return $token->access_token
            && $token->expires_at
            && $token->expires_at->gt(now()->addSeconds(self::REFRESH_THRESHOLD));
    }

    private function refreshAccessToken(ZaloOaToken $token): string
    {
        if (!$token->refresh_token) {
            throw new \RuntimeException('Zalo OA: chưa có refresh_token, seed qua `php artisan zalo:oa-token`');
        }

        $res = Http::timeout(15)
            ->asForm()
            ->withHeaders(['secret_key' => (string) config('services.zalo.oa_secret_key')])
            ->post(self::OAUTH_URL, [
                'app_id'        => config('services.zalo.oa_id'),
                'grant_type'    => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ])
            ->json();

        $newAccess  = $res['access_token'] ?? null;
        $newRefresh = $res['refresh_token'] ?? null;
        $expiresIn  = (int) ($res['expires_in'] ?? 0);

        if (!$newAccess || !$newRefresh) {
            Log::channel('zalo_notify')->error('[OA] refresh token thất bại', ['response' => $res]);
            throw new \RuntimeException('Zalo OA: refresh token thất bại — ' . json_encode($res));
        }

        $token->fill([
            'access_token'  => $newAccess,
            'refresh_token' => $newRefresh,
            'expires_at'    => now()->addSeconds($expiresIn > 0 ? $expiresIn : 90000),
        ])->save();

        Log::channel('zalo_notify')->info('[OA] đã refresh access_token', [
            'expires_at' => $token->expires_at->toDateTimeString(),
        ]);

        return $newAccess;
    }

    /**
     * Gửi tin giao dịch OA theo Zalo user_id (= customers.firebase_id).
     * Tin transaction không vướng cửa sổ tương tác 48h.
     *
     * @param array $payload Phần "message" của body (vd ['text' => '...'] hoặc template attachment).
     */
    public function sendOaMessage(string $userId, array $payload): array
    {
        return $this->dispatchSend(
            'OA',
            "{$this->apiBaseUrl}/v3.0/oa/message/transaction",
            [
                'recipient' => ['user_id' => $userId],
                'message'   => $payload,
            ],
            ['user_id' => $userId]
        );
    }

    /**
     * Gửi ZNS theo SĐT bằng template đã duyệt. SĐT tự chuẩn hoá về 84...
     */
    public function sendZns(string $phone, string $templateId, array $templateData): array
    {
        return $this->dispatchSend(
            'ZNS',
            self::ZNS_URL,
            [
                'phone'         => $this->normalizePhone($phone),
                'template_id'   => $templateId,
                'template_data' => (object) $templateData,
            ],
            ['phone' => $this->normalizePhone($phone), 'template_id' => $templateId]
        );
    }

    /**
     * Gửi request và chuẩn hoá kết quả. Zalo trả {error:0} khi OK, error!=0 khi lỗi.
     */
    private function dispatchSend(string $channel, string $url, array $body, array $logContext): array
    {
        try {
            $res = Http::timeout(15)
                ->withHeaders([
                    'access_token' => $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $body)
                ->json();
        } catch (\Throwable $e) {
            Log::channel('zalo_notify')->error("[{$channel}] gửi thất bại (exception)", $logContext + [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => null, 'message' => $e->getMessage()];
        }

        $error = $res['error'] ?? null;
        $ok    = $error === 0;

        Log::channel('zalo_notify')->log($ok ? 'info' : 'warning', "[{$channel}] " . ($ok ? 'OK' : 'lỗi'), $logContext + [
            'error'   => $error,
            'message' => $res['message'] ?? null,
        ]);

        return [
            'ok'      => $ok,
            'error'   => is_int($error) ? $error : null,
            'message' => $res['message'] ?? null,
        ];
    }

    /**
     * Chuẩn hoá SĐT VN về dạng 84xxxxxxxxx (ZNS yêu cầu).
     *   0905... → 84905...
     *   +84905... / 84905... → 84905...
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            return '84' . substr($digits, 1);
        }
        if (str_starts_with($digits, '84')) {
            return $digits;
        }

        return $digits;
    }
}
