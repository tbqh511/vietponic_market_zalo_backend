<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Nhận OA follow/unfollow webhook từ Zalo để cập nhật cờ customers.oa_followed.
 *
 * Route public (không JWT) — bảo vệ bằng chữ ký MAC:
 *   X-ZEvent-Signature = "mac=" . sha256(appId + rawBody + timestamp + oaSecretKey)
 * (theo Zalo OA webhook spec). So sánh bằng hash_equals chống timing attack.
 *
 * follower.id = Zalo user_id = customers.firebase_id. Chỉ cập nhật khi tìm thấy
 * customer (khách đã từng mở mini app) — còn lại bỏ qua an toàn, kênh ZNS vẫn
 * hoạt động độc lập.
 */
class ZaloOaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $rawBody   = $request->getContent();
        $signature = (string) $request->header('X-ZEvent-Signature', '');

        if (!$this->verifySignature($rawBody, $signature)) {
            Log::channel('zalo_notify')->warning('[OA webhook] chữ ký không hợp lệ', [
                'signature' => $signature,
            ]);
            return response()->json(['error' => true], 401);
        }

        $event     = (string) $request->input('event_name', '');
        $followerId = $request->input('follower.id') ?? data_get($request->all(), 'follower.id');

        if (!in_array($event, ['follow', 'unfollow'], true) || !$followerId) {
            // Event khác (user_send_text, ...) — không xử lý, trả 200 để Zalo không retry.
            return response()->json(['error' => false]);
        }

        $customer = Customer::where('firebase_id', $followerId)->first();
        if (!$customer) {
            Log::channel('zalo_notify')->info('[OA webhook] không map được follower → customer', [
                'event' => $event, 'follower_id' => $followerId,
            ]);
            return response()->json(['error' => false]);
        }

        if ($event === 'follow') {
            $customer->update(['oa_followed' => true, 'oa_followed_at' => now()]);
        } else {
            $customer->update(['oa_followed' => false]);
        }

        Log::channel('zalo_notify')->info('[OA webhook] cập nhật follow state', [
            'event' => $event, 'customer_id' => $customer->id,
        ]);

        return response()->json(['error' => false]);
    }

    private function verifySignature(string $rawBody, string $signature): bool
    {
        $secret = (string) config('services.zalo.oa_secret_key');
        $appId  = (string) config('services.zalo.app_id');
        if ($secret === '' || $signature === '') {
            return false;
        }

        // Zalo gửi signature dạng "mac=<hex>". timestamp nằm trong body (field "timestamp").
        $payload   = json_decode($rawBody, true) ?: [];
        $timestamp = (string) ($payload['timestamp'] ?? '');

        $expected = 'mac=' . hash('sha256', $appId . $rawBody . $timestamp . $secret);

        return hash_equals($expected, $signature);
    }
}
