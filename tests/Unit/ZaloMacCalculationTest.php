<?php

namespace Tests\Unit;

use App\Http\Controllers\ZaloApiController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * ZaloMacCalculationTest — chốt thuật toán MAC của prepare-order:
 *   ksort(params) → "k=v&..." (array/null → json_encode) → HMAC-SHA256(ZALO_CHECK_OUT_SECRET).
 *
 * Đây là HỢP ĐỒNG Zalo verify — không được đổi. Test gọi thẳng private
 * calculateMac qua reflection để bám đúng implementation thật.
 */
class ZaloMacCalculationTest extends TestCase
{
    private function calculateMac(array $params): string
    {
        $controller = app(ZaloApiController::class);
        $method = new ReflectionMethod(ZaloApiController::class, 'calculateMac');
        $method->setAccessible(true);
        return $method->invoke($controller, $params);
    }

    /** Replicate thuật toán tài liệu hoá để đối chiếu (pin format). */
    private function expectedMac(array $params): string
    {
        ksort($params);
        $dataMac = collect($params)
            ->map(fn ($value, $key) => $key . '=' . (is_array($value) || $value === null ? json_encode($value) : $value))
            ->implode('&');
        return hash_hmac('sha256', $dataMac, env('ZALO_CHECK_OUT_SECRET'));
    }

    public function test_mac_matches_documented_algorithm(): void
    {
        $params = [
            'amount'    => 150000,
            'desc'      => 'Thanh toan don hang #1',
            'item'      => [['id' => 1, 'amount' => 150000]],
            'extradata' => 'order:1',
            'method'    => 'BANK_SANDBOX',
        ];

        $this->assertSame($this->expectedMac($params), $this->calculateMac($params));
    }

    public function test_key_order_does_not_affect_mac(): void
    {
        $a = [
            'method'    => 'BANK_SANDBOX',
            'amount'    => 150000,
            'item'      => [['id' => 1, 'amount' => 150000]],
            'desc'      => 'Thanh toan',
            'extradata' => 'order:1',
        ];
        // Cùng nội dung nhưng thứ tự insert khác → ksort phải cho cùng MAC.
        $b = [
            'amount'    => 150000,
            'desc'      => 'Thanh toan',
            'extradata' => 'order:1',
            'item'      => [['id' => 1, 'amount' => 150000]],
            'method'    => 'BANK_SANDBOX',
        ];

        $this->assertSame($this->calculateMac($a), $this->calculateMac($b));
    }

    public function test_array_value_is_json_encoded_in_mac(): void
    {
        $params = [
            'amount' => 1000,
            'item'   => [['id' => 9, 'amount' => 1000]],
        ];

        // Chứng minh field array được json_encode trong chuỗi ký.
        $sorted = $params;
        ksort($sorted);
        $raw = 'amount=1000&item=' . json_encode($sorted['item']);
        $expected = hash_hmac('sha256', $raw, env('ZALO_CHECK_OUT_SECRET'));

        $this->assertSame($expected, $this->calculateMac($params));
    }

    public function test_different_amount_changes_mac(): void
    {
        $base = ['amount' => 1000, 'desc' => 'x'];
        $other = ['amount' => 2000, 'desc' => 'x'];

        $this->assertNotSame($this->calculateMac($base), $this->calculateMac($other));
    }
}
