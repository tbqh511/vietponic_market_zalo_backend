<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Models\ZaloOrder;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherValidationTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $service;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VoucherService::class);
        $this->customer = Customer::create([
            'name'        => 'Tester',
            'email'       => 'tester@zalo.user',
            'firebase_id' => 'tester-zid',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);
    }

    private function makeVoucher(array $override = []): Voucher
    {
        return Voucher::create(array_merge([
            'code'                  => 'SALE10',
            'name'                  => 'Giảm 10% đơn đầu',
            'discount_type'         => Voucher::TYPE_PERCENT,
            'discount_value'        => 10,
            'max_discount_amount'   => 50000,
            'min_order_amount'      => 0,
            'max_uses'              => null,
            'max_uses_per_customer' => 1,
            'used_count'            => 0,
            'is_active'             => true,
            'is_public'             => true,
        ], $override));
    }

    public function test_validates_happy_path_percent(): void
    {
        $this->makeVoucher();
        $res = $this->service->validate('SALE10', $this->customer->id, 200000, 30000);
        $this->assertTrue($res['ok']);
        $this->assertSame(20000, $res['discount_subtotal']);
        $this->assertSame(0, $res['discount_shipping']);
    }

    public function test_rejects_unknown_code(): void
    {
        $res = $this->service->validate('NOPE', $this->customer->id, 100000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('không tồn tại', $res['error']);
    }

    public function test_rejects_empty_code(): void
    {
        $res = $this->service->validate('   ', $this->customer->id, 100000, 0);
        $this->assertFalse($res['ok']);
    }

    public function test_rejects_inactive(): void
    {
        $this->makeVoucher(['is_active' => false]);
        $res = $this->service->validate('SALE10', $this->customer->id, 200000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('tạm dừng', $res['error']);
    }

    public function test_rejects_expired(): void
    {
        $this->makeVoucher([
            'valid_to' => Carbon::now()->subDay(),
        ]);
        $res = $this->service->validate('SALE10', $this->customer->id, 200000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('hết hạn', $res['error']);
    }

    public function test_rejects_not_yet_active(): void
    {
        $this->makeVoucher([
            'valid_from' => Carbon::now()->addDay(),
        ]);
        $res = $this->service->validate('SALE10', $this->customer->id, 200000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('chưa đến thời gian', $res['error']);
    }

    public function test_rejects_below_min_order(): void
    {
        $this->makeVoucher(['min_order_amount' => 300000]);
        $res = $this->service->validate('SALE10', $this->customer->id, 100000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Đơn tối thiểu', $res['error']);
    }

    public function test_rejects_when_global_uses_exhausted(): void
    {
        $this->makeVoucher(['max_uses' => 5, 'used_count' => 5]);
        $res = $this->service->validate('SALE10', $this->customer->id, 200000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('hết lượt', $res['error']);
    }

    public function test_rejects_when_customer_already_used(): void
    {
        $v = $this->makeVoucher(['max_uses_per_customer' => 1]);

        // Tạo order tối thiểu để satisfy FK voucher_redemptions.order_id.
        $order = ZaloOrder::create([
            'status'         => 'delivered',
            'payment_status' => 'cod',
            'created_at'     => Carbon::now(),
            'received_at'    => Carbon::now(),
            'total'          => 200000,
            'subtotal'       => 200000,
            'shipping_fee'   => 0,
            'customer_id'    => $this->customer->id,
        ]);

        VoucherRedemption::create([
            'voucher_id'      => $v->id,
            'order_id'        => $order->id,
            'customer_id'     => $this->customer->id,
            'discount_amount' => 1000,
            'redeemed_at'     => Carbon::now(),
        ]);
        $res = $this->service->validate('SALE10', $this->customer->id, 200000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('đã sử dụng', $res['error']);
    }

    public function test_free_shipping_rejected_when_no_shipping_fee(): void
    {
        $this->makeVoucher([
            'code'           => 'FREESHIP',
            'discount_type'  => Voucher::TYPE_FREE_SHIPPING,
            'discount_value' => 0,
        ]);
        $res = $this->service->validate('FREESHIP', $this->customer->id, 200000, 0);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('giao hàng', $res['error']);
    }

    public function test_code_case_insensitive(): void
    {
        $this->makeVoucher();
        $res = $this->service->validate('sale10', $this->customer->id, 200000, 0);
        $this->assertTrue($res['ok']);
    }
}
