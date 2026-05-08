<?php

namespace Tests\Feature;

use App\Http\Middleware\ZaloJwtMiddleware;
use Tests\ZaloTestCase;

/**
 * Integration test cho endpoint POST /api/prepare-order.
 *
 * Endpoint này nằm sau middleware zalo.jwt, nhưng bản thân logic prepareOrder()
 * không sử dụng customer_id – chỉ tính MAC. Ta bypass middleware trong test.
 *
 * Env cần thiết: ZALO_CHECK_OUT_SECRET (set trong phpunit.xml)
 */
class PrepareOrderTest extends ZaloTestCase
{
    /** Bỏ qua JWT middleware vì prepareOrder không cần customer context */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ZaloJwtMiddleware::class);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /** Payload hợp lệ đầy đủ để tái sử dụng */
    private function validPayload(): array
    {
        return [
            'amount'    => 50000,
            'desc'      => 'Thanh toan don hang Vietponic #001',
            'item'      => [
                [
                    'id'       => 1,
                    'name'     => 'Rau cải xanh thủy canh',
                    'price'    => 25000,
                    'quantity' => 2,
                    'amount'   => 50000,
                ],
            ],
            'extradata' => 'orderId=1',
            'method'    => 'COD',
        ];
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    /**
     * Payload hợp lệ phải trả về { error: false, mac: "<64-char hex>" }.
     * Đây là contract chính giữa backend và Zalo Checkout SDK.
     */
    public function test_valid_payload_returns_mac_and_order_data(): void
    {
        $response = $this->postJson('/api/prepare-order', $this->validPayload());

        $response->assertStatus(200)
            ->assertJsonStructure(['error', 'mac', 'orderData'])
            ->assertJson(['error' => false]);

        $mac = $response->json('mac');
        $this->assertIsString($mac);
        $this->assertSame(64, strlen($mac), 'MAC phải là HMAC-SHA256 hex 64 ký tự');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $mac);
    }

    /**
     * MAC trả về phải trùng với giá trị tính thủ công theo đúng thuật toán Zalo.
     * Test này đảm bảo server đang tính đúng – không chỉ "trả về gì đó có 64 ký tự".
     */
    public function test_returned_mac_matches_expected_hmac_computation(): void
    {
        $payload = $this->validPayload();
        $secret  = env('ZALO_CHECK_OUT_SECRET');

        // Tính MAC kỳ vọng: ksort → "key=value" → HMAC-SHA256
        $data = $payload;
        ksort($data);
        $parts = [];
        foreach ($data as $key => $value) {
            $parts[] = $key . '=' . (is_array($value) ? json_encode($value) : $value);
        }
        $expectedMac = hash_hmac('sha256', implode('&', $parts), $secret);

        $response = $this->postJson('/api/prepare-order', $payload);

        $response->assertStatus(200);
        $this->assertSame($expectedMac, $response->json('mac'));
    }

    /**
     * orderData trong response phải chứa đúng các field đã gửi.
     */
    public function test_order_data_contains_submitted_fields(): void
    {
        $payload  = $this->validPayload();
        $response = $this->postJson('/api/prepare-order', $payload);

        $response->assertStatus(200);
        $orderData = $response->json('orderData');

        $this->assertSame($payload['amount'], $orderData['amount']);
        $this->assertSame($payload['desc'], $orderData['desc']);
        $this->assertSame($payload['method'], $orderData['method']);
    }

    // ─── Validation errors ────────────────────────────────────────────────────

    /**
     * Thiếu field `amount` → HTTP 422 Unprocessable Entity.
     * Zalo SDK luôn cần amount để tạo giao dịch.
     */
    public function test_missing_amount_returns_422(): void
    {
        $payload = $this->validPayload();
        unset($payload['amount']);

        $this->postJson('/api/prepare-order', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('amount');
    }

    /**
     * Thiếu field `desc` → HTTP 422.
     */
    public function test_missing_desc_returns_422(): void
    {
        $payload = $this->validPayload();
        unset($payload['desc']);

        $this->postJson('/api/prepare-order', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('desc');
    }

    /**
     * Thiếu field `item` → HTTP 422.
     * Item array là bắt buộc để hiển thị chi tiết đơn hàng trong app Zalo.
     */
    public function test_missing_item_returns_422(): void
    {
        $payload = $this->validPayload();
        unset($payload['item']);

        $this->postJson('/api/prepare-order', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('item');
    }

    /**
     * Item thiếu field `amount` bắt buộc → HTTP 422.
     * Zalo yêu cầu item[].amount để tính tổng tiền từng mặt hàng.
     */
    public function test_item_missing_required_amount_returns_422(): void
    {
        $payload          = $this->validPayload();
        $payload['item']  = [['id' => 1, 'name' => 'Rau', 'quantity' => 1]]; // thiếu amount

        $this->postJson('/api/prepare-order', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('item.0.amount');
    }

    /**
     * `amount` không phải số → HTTP 422.
     */
    public function test_non_numeric_amount_returns_422(): void
    {
        $payload           = $this->validPayload();
        $payload['amount'] = 'khong_phai_so';

        $this->postJson('/api/prepare-order', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('amount');
    }

    /**
     * Khi ZALO_CHECK_OUT_SECRET chưa được cấu hình, server phải trả lỗi 500.
     * Đây là lỗi cấu hình server – không phải lỗi client.
     */
    public function test_missing_env_secret_returns_500(): void
    {
        // Tạm thời xóa secret để mô phỏng môi trường chưa cấu hình
        $original = env('ZALO_CHECK_OUT_SECRET');
        putenv('ZALO_CHECK_OUT_SECRET=');
        $_ENV['ZALO_CHECK_OUT_SECRET']    = '';
        $_SERVER['ZALO_CHECK_OUT_SECRET'] = '';

        try {
            $response = $this->postJson('/api/prepare-order', $this->validPayload());
            $response->assertStatus(500);
        } finally {
            putenv("ZALO_CHECK_OUT_SECRET={$original}");
            $_ENV['ZALO_CHECK_OUT_SECRET']    = $original;
            $_SERVER['ZALO_CHECK_OUT_SECRET'] = $original;
        }
    }
}
