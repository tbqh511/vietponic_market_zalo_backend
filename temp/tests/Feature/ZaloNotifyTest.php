<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ZaloTestCase;

/**
 * Integration test cho endpoint POST /api/notify (notifySDK).
 *
 * Đây là webhook nhận callback từ Zalo Checkout SDK sau khi người dùng thanh toán.
 * Server phải xác thực MAC trước khi cập nhật trạng thái đơn hàng,
 * tránh attacker giả mạo callback để đánh dấu đơn hàng là đã thanh toán.
 *
 * Cấu trúc request thật từ Zalo:
 *   POST /api/notify
 *   { "data": { ... full payload ... }, "overallMac": "...", "mac": "..." }
 *
 * MAC được Zalo tính: hash_hmac('sha256', ksort(data) → "k1=v1&k2=v2&...", ZALO_CHECK_OUT_SECRET)
 * Verified bằng cách compute offline với payload thật từ MoMo Sandbox.
 */
class ZaloNotifyTest extends ZaloTestCase
{

    private const CHECKOUT_SECRET = 'test_checkout_secret_key_for_testing';
    private const ZALO_APP_ID     = 'test_mini_app_id_12345';
    private const SDK_ORDER_ID    = 'zalo_sdk_order_abc123';
    private const PAYMENT_METHOD  = 'COD';

    /** Tạo một ZaloOrder test với checkout_sdk_order_id đã biết */
    private function createTestOrder(array $attributes = []): int
    {
        $id = DB::table('zalo_orders')->insertGetId(array_merge([
            'status'                => 'pending',
            'payment_status'        => 'pending',
            'total'                 => 50000,
            'note'                  => '',
            'customer_id'           => null,
            'payment_method'        => null,
            'checkout_sdk_order_id' => self::SDK_ORDER_ID,
            'created_at'            => now(),
        ], $attributes));

        return $id;
    }

    /**
     * Tính MAC đúng theo thuật toán Zalo: ksort(data) → "k1=v1&k2=v2&..." → HMAC-SHA256.
     */
    private function computeMac(array $data): string
    {
        ksort($data);
        $parts = [];
        foreach ($data as $k => $v) {
            $parts[] = "{$k}={$v}";
        }
        return hash_hmac('sha256', implode('&', $parts), self::CHECKOUT_SECRET);
    }

    /** Helper trả data array đầy đủ giống payload thật từ Zalo. */
    private function makeData(array $overrides = []): array
    {
        return array_merge([
            'appId'   => self::ZALO_APP_ID,
            'orderId' => self::SDK_ORDER_ID,
            'method'  => self::PAYMENT_METHOD,
        ], $overrides);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    /**
     * Khi MAC hợp lệ và đơn hàng tồn tại → trả về returnCode=1 và cập nhật payment_method.
     * Đây là luồng chính: người dùng thanh toán thành công, Zalo gọi về server.
     */
    public function test_valid_mac_returns_return_code_1_and_updates_order(): void
    {
        $orderId = $this->createTestOrder();
        $data = $this->makeData();

        $response = $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->computeMac($data),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 1, 'returnMessage' => 'Success']);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => self::PAYMENT_METHOD,
            'payment_status' => 'success',
        ]);
    }

    /**
     * Payload thật của Zalo có thêm fields (amount, resultCode, transId...).
     * MAC vẫn phải được verify đúng vì algorithm tính trên TOÀN BỘ data sau khi ksort.
     */
    public function test_valid_mac_with_full_payload_updates_order(): void
    {
        $orderId = $this->createTestOrder();
        $data = $this->makeData([
            'amount'         => 15000,
            'method'         => 'MOMO_SANDBOX',
            'transId'        => '260511_0001160186878',
            'extradata'      => '%7B%22myOrderId%22%3A1%7D',
            'resultCode'     => 1,
            'message'        => 'Thành công.',
            'paymentChannel' => 'MOMO_SANDBOX',
        ]);

        $response = $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->computeMac($data),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 1]);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => 'MOMO_SANDBOX',
            'payment_status' => 'success',
        ]);
    }

    /**
     * resultCode = -1 → giao dịch thất bại → payment_status = 'failed'.
     */
    public function test_result_code_minus_1_marks_payment_failed(): void
    {
        $orderId = $this->createTestOrder();
        $data = $this->makeData([
            'method'     => 'MOMO_SANDBOX',
            'resultCode' => -1,
        ]);

        $response = $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->computeMac($data),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 1]);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_status' => 'failed',
        ]);
    }

    // ─── Security: MAC validation ─────────────────────────────────────────────

    /**
     * MAC sai → trả về returnCode=0 và KHÔNG cập nhật đơn hàng.
     * Đây là case quan trọng nhất về bảo mật: ngăn attacker giả mạo callback.
     */
    public function test_invalid_mac_returns_return_code_0_and_does_not_update_order(): void
    {
        $orderId = $this->createTestOrder();
        $originalMethod = DB::table('zalo_orders')->where('id', $orderId)->value('payment_method');

        $response = $this->postJson('/api/notify', [
            'data'       => $this->makeData(),
            'overallMac' => str_repeat('a', 64),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 0]);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => $originalMethod,
            'payment_status' => 'pending',
        ]);
    }

    /**
     * MAC được tính với data khác (ví dụ method khác) → phải bị từ chối.
     * Phát hiện attacker cố tình thay đổi nội dung nhưng giữ nguyên MAC của request cũ.
     */
    public function test_mac_computed_with_different_data_is_rejected(): void
    {
        $this->createTestOrder();

        $macForBank = $this->computeMac($this->makeData(['method' => 'BANK']));

        $response = $this->postJson('/api/notify', [
            'data'       => $this->makeData(['method' => 'COD']),
            'overallMac' => $macForBank,
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 0]);
    }

    // ─── Missing / malformed data ─────────────────────────────────────────────

    /**
     * Request thiếu field `data` → returnCode=0.
     */
    public function test_missing_data_field_returns_return_code_0(): void
    {
        $this->postJson('/api/notify', [
            'overallMac' => $this->computeMac($this->makeData()),
        ])
            ->assertStatus(200)
            ->assertJson(['returnCode' => 0]);
    }

    /**
     * Request thiếu field `overallMac` (và không có fallback `mac`) → returnCode=0.
     */
    public function test_missing_mac_field_returns_return_code_0(): void
    {
        $this->postJson('/api/notify', [
            'data' => $this->makeData(),
        ])
            ->assertStatus(200)
            ->assertJson(['returnCode' => 0]);
    }

    /**
     * orderId không tồn tại trong DB → returnCode=0.
     * MAC hợp lệ nhưng không có đơn hàng nào khớp.
     */
    public function test_nonexistent_order_id_returns_return_code_0(): void
    {
        $data = $this->makeData(['orderId' => 'order_that_does_not_exist_xyz']);

        $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->computeMac($data),
        ])
            ->assertStatus(200)
            ->assertJson(['returnCode' => 0, 'returnMessage' => 'Order not found']);
    }

    /**
     * Method ZALOPAY_SANDBOX (Ví ZaloPay - Sandbox) → returnCode=1 và cập nhật payment_method.
     * Channel này được Zalo Mini App cấu hình ở Console (Merchant App ID 2553) và
     * đi qua cùng webhook /notify như COD/BANK, không cần luồng xử lý riêng.
     */
    public function test_zalopay_sandbox_method_is_accepted_and_updates_order(): void
    {
        $orderId = $this->createTestOrder();
        $data = $this->makeData(['method' => 'ZALOPAY_SANDBOX']);

        $response = $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->computeMac($data),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 1, 'returnMessage' => 'Success']);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => 'ZALOPAY_SANDBOX',
        ]);
    }

    /**
     * Method MOMO_SANDBOX (Ví MoMo - Sandbox) → returnCode=1 và cập nhật payment_method.
     * Channel này được Zalo Mini App cấu hình ở Console (PartnerCode MOMO, AccessKey/SecretKey)
     * và đi qua cùng webhook /notify như COD/BANK/ZALOPAY, không cần luồng xử lý riêng.
     */
    public function test_momo_sandbox_method_is_accepted_and_updates_order(): void
    {
        $orderId = $this->createTestOrder();
        $data = $this->makeData(['method' => 'MOMO_SANDBOX']);

        $response = $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->computeMac($data),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 1, 'returnMessage' => 'Success']);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => 'MOMO_SANDBOX',
        ]);
    }

    /**
     * Method không hợp lệ (không trong danh sách whitelist) → returnCode=0.
     */
    public function test_invalid_payment_method_returns_return_code_0(): void
    {
        $this->createTestOrder();
        $data = $this->makeData(['method' => 'PAYPAL']);

        $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->computeMac($data),
        ])
            ->assertStatus(200)
            ->assertJson(['returnCode' => 0]);
    }

    /**
     * Khi ZALO_CHECK_OUT_SECRET chưa cấu hình → returnCode=0 (không crash server).
     * Server cấu hình sai không được cập nhật đơn hàng một cách bừa bãi.
     */
    public function test_missing_checkout_secret_env_returns_return_code_0(): void
    {
        $this->createTestOrder();

        $original = env('ZALO_CHECK_OUT_SECRET');
        putenv('ZALO_CHECK_OUT_SECRET=');
        $_ENV['ZALO_CHECK_OUT_SECRET']    = '';
        $_SERVER['ZALO_CHECK_OUT_SECRET'] = '';

        try {
            $data = $this->makeData();
            $response = $this->postJson('/api/notify', [
                'data'       => $data,
                'overallMac' => $this->computeMac($data),
            ]);

            $response->assertStatus(200)
                ->assertJson(['returnCode' => 0]);
        } finally {
            putenv("ZALO_CHECK_OUT_SECRET={$original}");
            $_ENV['ZALO_CHECK_OUT_SECRET']    = $original;
            $_SERVER['ZALO_CHECK_OUT_SECRET'] = $original;
        }
    }
}
