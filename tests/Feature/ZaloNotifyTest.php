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
 * Cấu trúc request:
 *   POST /api/notify
 *   { "data": { "appId": "...", "orderId": "...", "method": "COD" }, "mac": "..." }
 *
 * MAC được Zalo tính: hash_hmac('sha256', "appId=X&orderId=Y&method=Z", ZALO_APP_SECRET)
 */
class ZaloNotifyTest extends ZaloTestCase
{

    private const APP_SECRET      = 'test_app_secret_for_testing';
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
     * Tính MAC đúng theo thuật toán Zalo để dùng trong test case hợp lệ.
     * Format: hash_hmac('sha256', "appId=X&orderId=Y&method=Z", ZALO_APP_SECRET)
     */
    private function computeValidMac(
        string $appId    = self::ZALO_APP_ID,
        string $orderId  = self::SDK_ORDER_ID,
        string $method   = self::PAYMENT_METHOD
    ): string {
        $raw = "appId={$appId}&orderId={$orderId}&method={$method}";
        return hash_hmac('sha256', $raw, self::APP_SECRET);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    /**
     * Khi MAC hợp lệ và đơn hàng tồn tại → trả về returnCode=1 và cập nhật payment_method.
     * Đây là luồng chính: người dùng thanh toán thành công, Zalo gọi về server.
     */
    public function test_valid_mac_returns_return_code_1_and_updates_order(): void
    {
        $orderId = $this->createTestOrder();

        $response = $this->postJson('/api/notify', [
            'data' => [
                'appId'   => self::ZALO_APP_ID,
                'orderId' => self::SDK_ORDER_ID,
                'method'  => self::PAYMENT_METHOD,
            ],
            'mac' => $this->computeValidMac(),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 1, 'returnMessage' => 'Success']);

        // Đơn hàng phải được cập nhật payment_method
        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => self::PAYMENT_METHOD,
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
            'data' => [
                'appId'   => self::ZALO_APP_ID,
                'orderId' => self::SDK_ORDER_ID,
                'method'  => self::PAYMENT_METHOD,
            ],
            'mac' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', // MAC giả
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 0]);

        // Đơn hàng KHÔNG được thay đổi
        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => $originalMethod, // vẫn null
        ]);
    }

    /**
     * MAC được tính với data khác (ví dụ method khác) → phải bị từ chối.
     * Phát hiện attacker cố tình thay đổi nội dung nhưng giữ nguyên MAC của request cũ.
     */
    public function test_mac_computed_with_different_data_is_rejected(): void
    {
        $this->createTestOrder();

        // MAC đúng cho method=BANK nhưng payload gửi method=COD
        $macForBank = $this->computeValidMac(
            appId:   self::ZALO_APP_ID,
            orderId: self::SDK_ORDER_ID,
            method:  'BANK'
        );

        $response = $this->postJson('/api/notify', [
            'data' => [
                'appId'   => self::ZALO_APP_ID,
                'orderId' => self::SDK_ORDER_ID,
                'method'  => 'COD', // khác với method dùng để tính MAC
            ],
            'mac' => $macForBank,
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
            'mac' => $this->computeValidMac(),
        ])
            ->assertStatus(200)
            ->assertJson(['returnCode' => 0]);
    }

    /**
     * Request thiếu field `mac` → returnCode=0.
     */
    public function test_missing_mac_field_returns_return_code_0(): void
    {
        $this->postJson('/api/notify', [
            'data' => [
                'appId'   => self::ZALO_APP_ID,
                'orderId' => self::SDK_ORDER_ID,
                'method'  => self::PAYMENT_METHOD,
            ],
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
        $nonExistentSdkId = 'order_that_does_not_exist_xyz';
        $mac = hash_hmac(
            'sha256',
            "appId=" . self::ZALO_APP_ID . "&orderId={$nonExistentSdkId}&method=" . self::PAYMENT_METHOD,
            self::APP_SECRET
        );

        $this->postJson('/api/notify', [
            'data' => [
                'appId'   => self::ZALO_APP_ID,
                'orderId' => $nonExistentSdkId,
                'method'  => self::PAYMENT_METHOD,
            ],
            'mac' => $mac,
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

        $response = $this->postJson('/api/notify', [
            'data' => [
                'appId'   => self::ZALO_APP_ID,
                'orderId' => self::SDK_ORDER_ID,
                'method'  => 'ZALOPAY_SANDBOX',
            ],
            'mac' => $this->computeValidMac(method: 'ZALOPAY_SANDBOX'),
        ]);

        $response->assertStatus(200)
            ->assertJson(['returnCode' => 1, 'returnMessage' => 'Success']);

        $this->assertDatabaseHas('zalo_orders', [
            'id'             => $orderId,
            'payment_method' => 'ZALOPAY_SANDBOX',
        ]);
    }

    /**
     * Method không hợp lệ (không trong danh sách whitelist) → returnCode=0.
     * Ngăn giao dịch với phương thức thanh toán không được hỗ trợ.
     */
    public function test_invalid_payment_method_returns_return_code_0(): void
    {
        $this->createTestOrder();

        $invalidMethod = 'PAYPAL'; // không có trong whitelist
        $mac = hash_hmac(
            'sha256',
            "appId=" . self::ZALO_APP_ID . "&orderId=" . self::SDK_ORDER_ID . "&method={$invalidMethod}",
            self::APP_SECRET
        );

        $this->postJson('/api/notify', [
            'data' => [
                'appId'   => self::ZALO_APP_ID,
                'orderId' => self::SDK_ORDER_ID,
                'method'  => $invalidMethod,
            ],
            'mac' => $mac,
        ])
            ->assertStatus(200)
            ->assertJson(['returnCode' => 0]);
    }

    /**
     * Khi ZALO_APP_SECRET chưa cấu hình → returnCode=0 (không crash server).
     * Server cấu hình sai không được cập nhật đơn hàng một cách bừa bãi.
     */
    public function test_missing_app_secret_env_returns_return_code_0(): void
    {
        $this->createTestOrder();

        $original = env('ZALO_APP_SECRET');
        putenv('ZALO_APP_SECRET=');
        $_ENV['ZALO_APP_SECRET']    = '';
        $_SERVER['ZALO_APP_SECRET'] = '';

        try {
            $response = $this->postJson('/api/notify', [
                'data' => [
                    'appId'   => self::ZALO_APP_ID,
                    'orderId' => self::SDK_ORDER_ID,
                    'method'  => self::PAYMENT_METHOD,
                ],
                'mac' => $this->computeValidMac(),
            ]);

            $response->assertStatus(200)
                ->assertJson(['returnCode' => 0]);
        } finally {
            putenv("ZALO_APP_SECRET={$original}");
            $_ENV['ZALO_APP_SECRET']    = $original;
            $_SERVER['ZALO_APP_SECRET'] = $original;
        }
    }
}
