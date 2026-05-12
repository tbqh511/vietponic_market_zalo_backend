<?php

namespace Tests\Unit;

use App\Http\Controllers\ZaloApiController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit test cho hàm calculateMac() trong ZaloApiController.
 *
 * calculateMac() là private method, nên dùng PHP Reflection để truy cập.
 * Không cần database – chỉ test logic tính toán thuần túy.
 */
class ZaloMacCalculationTest extends TestCase
{
    private ZaloApiController $controller;
    private ReflectionMethod $calculateMac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ZaloApiController();

        $this->calculateMac = new ReflectionMethod(ZaloApiController::class, 'calculateMac');
        $this->calculateMac->setAccessible(true);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /** Gọi calculateMac với array params cho trước, trả về chuỗi MAC */
    private function mac(array $params): string
    {
        return $this->calculateMac->invoke($this->controller, $params);
    }

    /** Payload mẫu hợp lệ để tái sử dụng trong nhiều test */
    private function sampleParams(): array
    {
        return [
            'amount'    => 50000,
            'desc'      => 'Thanh toan don hang #001',
            'item'      => [['id' => 1, 'name' => 'Rau cải xanh', 'price' => 25000, 'quantity' => 2, 'amount' => 50000]],
            'extradata' => 'orderId=1',
            'method'    => 'COD',
        ];
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    /**
     * Kết quả phải là chuỗi hex 64 ký tự.
     * HMAC-SHA256 cho ra 32 bytes = 64 ký tự hex.
     */
    public function test_mac_is_64_character_hex_string(): void
    {
        $mac = $this->mac($this->sampleParams());

        $this->assertIsString($mac);
        $this->assertSame(64, strlen($mac));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $mac, 'MAC phải là chuỗi hex viết thường');
    }

    /**
     * Cùng input → cùng output (idempotent / deterministic).
     * Gọi hai lần với cùng params phải ra cùng giá trị MAC.
     */
    public function test_mac_is_deterministic_for_same_input(): void
    {
        $params = $this->sampleParams();

        $mac1 = $this->mac($params);
        $mac2 = $this->mac($params);

        $this->assertSame($mac1, $mac2);
    }

    /**
     * ksort được áp dụng: thứ tự key trong array truyền vào không được ảnh hưởng kết quả.
     * Đây là yêu cầu quan trọng của Zalo – params phải được sort trước khi ghép chuỗi.
     */
    public function test_mac_is_same_regardless_of_input_key_order(): void
    {
        // Thứ tự key khác nhau (đảo ngược so với alphabetical)
        $paramsAsc = [
            'amount'    => 50000,
            'desc'      => 'Thanh toan',
            'extradata' => 'x',
            'item'      => [['id' => 1, 'amount' => 50000]],
            'method'    => 'COD',
        ];

        $paramsReversed = [
            'method'    => 'COD',
            'item'      => [['id' => 1, 'amount' => 50000]],
            'extradata' => 'x',
            'desc'      => 'Thanh toan',
            'amount'    => 50000,
        ];

        $this->assertSame(
            $this->mac($paramsAsc),
            $this->mac($paramsReversed),
            'ksort phải được áp dụng để MAC không phụ thuộc vào thứ tự key đầu vào'
        );
    }

    /**
     * Chuỗi dataMac phải có dạng: amount=...&desc=...&extradata=...&item=[...]&method=...
     * Kiểm tra gián tiếp: nếu ksort đúng, thứ tự keys trong chuỗi phải theo alphabet.
     * Dùng cách verify: thay đổi value của một field → MAC phải thay đổi.
     */
    public function test_changing_amount_changes_mac(): void
    {
        $base   = $this->sampleParams();
        $altered = array_merge($base, ['amount' => 99999]);

        $this->assertNotSame($this->mac($base), $this->mac($altered));
    }

    /**
     * Thay đổi desc → MAC phải khác.
     */
    public function test_changing_desc_changes_mac(): void
    {
        $base    = $this->sampleParams();
        $altered = array_merge($base, ['desc' => 'Don hang khac']);

        $this->assertNotSame($this->mac($base), $this->mac($altered));
    }

    /**
     * Thay đổi item array → MAC phải khác.
     * Item được JSON-encode trước khi ghép chuỗi.
     */
    public function test_changing_item_changes_mac(): void
    {
        $base    = $this->sampleParams();
        $altered = array_merge($base, [
            'item' => [['id' => 99, 'name' => 'San pham khac', 'amount' => 50000]],
        ]);

        $this->assertNotSame($this->mac($base), $this->mac($altered));
    }

    /**
     * Thay đổi method → MAC phải khác.
     */
    public function test_changing_method_changes_mac(): void
    {
        $base    = $this->sampleParams();
        $altered = array_merge($base, ['method' => 'BANK']);

        $this->assertNotSame($this->mac($base), $this->mac($altered));
    }

    /**
     * Khi ZALO_CHECK_OUT_SECRET chưa được cấu hình, phải ném Exception.
     * Controller không nên tự silently fail – exception để caller biết cấu hình sai.
     */
    public function test_throws_exception_when_secret_is_missing(): void
    {
        // Tạm thời xóa env var
        $original = env('ZALO_CHECK_OUT_SECRET');
        putenv('ZALO_CHECK_OUT_SECRET=');
        $_ENV['ZALO_CHECK_OUT_SECRET']    = '';
        $_SERVER['ZALO_CHECK_OUT_SECRET'] = '';

        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessageMatches('/ZALO_CHECK_OUT_SECRET/');

            $this->mac($this->sampleParams());
        } finally {
            // Khôi phục env để không ảnh hưởng các test khác
            putenv("ZALO_CHECK_OUT_SECRET={$original}");
            $_ENV['ZALO_CHECK_OUT_SECRET']    = $original;
            $_SERVER['ZALO_CHECK_OUT_SECRET'] = $original;
        }
    }

    /**
     * Verify chuỗi dataMac được xây dựng đúng format.
     * Tính MAC thủ công từ bên ngoài và so sánh với kết quả hàm.
     */
    public function test_mac_matches_manually_computed_hmac_sha256(): void
    {
        $secret = env('ZALO_CHECK_OUT_SECRET');

        $params = [
            'amount'    => 50000,
            'desc'      => 'Test order',
            'extradata' => 'ref=001',
            'item'      => [['id' => 1, 'amount' => 50000]],
            'method'    => 'COD',
        ];

        // Tự tính MAC: sort key → ghép "key=value" → HMAC-SHA256
        $sorted = $params;
        ksort($sorted);
        $parts = [];
        foreach ($sorted as $key => $value) {
            $parts[] = $key . '=' . (is_array($value) ? json_encode($value) : $value);
        }
        $dataMac      = implode('&', $parts);
        $expectedMac  = hash_hmac('sha256', $dataMac, $secret);

        $this->assertSame($expectedMac, $this->mac($params));
    }
}
