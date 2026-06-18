<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherCalculationTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VoucherService::class);
    }

    private function voucher(array $attrs): Voucher
    {
        return new Voucher(array_merge([
            'code'                  => 'TEST',
            'name'                  => 'Test',
            'min_order_amount'      => 0,
            'max_uses_per_customer' => 1,
            'used_count'            => 0,
            'is_active'             => true,
            'is_public'             => true,
        ], $attrs));
    }

    public function test_percent_with_max_cap(): void
    {
        $v = $this->voucher([
            'discount_type'       => Voucher::TYPE_PERCENT,
            'discount_value'      => 10,
            'max_discount_amount' => 50000,
        ]);
        // 10% của 1.000.000 = 100.000 → cap 50.000
        $this->assertSame(50000, $this->service->calculateDiscount($v, 1000000, 30000));
    }

    public function test_percent_without_cap(): void
    {
        $v = $this->voucher([
            'discount_type'       => Voucher::TYPE_PERCENT,
            'discount_value'      => 15,
            'max_discount_amount' => null,
        ]);
        $this->assertSame(30000, $this->service->calculateDiscount($v, 200000, 0));
    }

    public function test_percent_rounds_down(): void
    {
        $v = $this->voucher([
            'discount_type'       => Voucher::TYPE_PERCENT,
            'discount_value'      => 10,
            'max_discount_amount' => null,
        ]);
        // 10% của 28.000 = 2.800 → floor giữ 2.800
        $this->assertSame(2800, $this->service->calculateDiscount($v, 28000, 0));
    }

    public function test_fixed_amount(): void
    {
        $v = $this->voucher([
            'discount_type'  => Voucher::TYPE_FIXED,
            'discount_value' => 20000,
        ]);
        $this->assertSame(20000, $this->service->calculateDiscount($v, 100000, 30000));
    }

    public function test_fixed_capped_by_subtotal(): void
    {
        $v = $this->voucher([
            'discount_type'  => Voucher::TYPE_FIXED,
            'discount_value' => 50000,
        ]);
        // Không cho giảm vượt subtotal — tránh total âm.
        $this->assertSame(30000, $this->service->calculateDiscount($v, 30000, 20000));
    }

    public function test_free_shipping_full(): void
    {
        $v = $this->voucher([
            'discount_type'  => Voucher::TYPE_FREE_SHIPPING,
            'discount_value' => 0, // value=0 → free toàn bộ
        ]);
        $b = $this->service->breakdown($v, 200000, 41800);
        $this->assertSame(0, $b['subtotal']);
        $this->assertSame(41800, $b['shipping']);
    }

    public function test_free_shipping_with_cap(): void
    {
        $v = $this->voucher([
            'discount_type'  => Voucher::TYPE_FREE_SHIPPING,
            'discount_value' => 30000, // value>0 → cap
        ]);
        $b = $this->service->breakdown($v, 200000, 50000);
        $this->assertSame(30000, $b['shipping']);
    }

    public function test_free_shipping_zero_when_no_shipping_fee(): void
    {
        $v = $this->voucher([
            'discount_type'  => Voucher::TYPE_FREE_SHIPPING,
            'discount_value' => 0,
        ]);
        $this->assertSame(0, $this->service->calculateDiscount($v, 200000, 0));
    }

    public function test_free_shipping_value_zero_with_max_discount_cap(): void
    {
        // Bug case: admin đặt value=0 (free toàn bộ) + max_discount_amount=50000 (cap)
        // Expect: giảm tối đa 50k dù ship thực tế 122k
        $v = $this->voucher([
            'discount_type'       => Voucher::TYPE_FREE_SHIPPING,
            'discount_value'      => 0,
            'max_discount_amount' => 50000,
        ]);
        $b = $this->service->breakdown($v, 1560000, 122059);
        $this->assertSame(0,     $b['subtotal']);
        $this->assertSame(50000, $b['shipping']);
    }

    public function test_free_shipping_value_with_cap_also_respects_max_discount(): void
    {
        // value=30000 cap từ discount_value, max_discount_amount=20000 cap chặt hơn → 20k
        $v = $this->voucher([
            'discount_type'       => Voucher::TYPE_FREE_SHIPPING,
            'discount_value'      => 30000,
            'max_discount_amount' => 20000,
        ]);
        $b = $this->service->breakdown($v, 200000, 50000);
        $this->assertSame(20000, $b['shipping']);
    }
}
