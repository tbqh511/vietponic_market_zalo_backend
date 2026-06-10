<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Voucher;
use Database\Seeders\VoucherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ORDER-10 (B9): seeder voucher tạo đúng GIAM20K và Voucher::isUsable cư xử đúng
 * theo "Kết quả mong đợi" — đơn dưới 100k bị chặn, đơn từ 100k dùng được.
 */
class VoucherSeederTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VoucherSeeder::class);
        $this->customer = Customer::create([
            'name'        => 'Tester',
            'email'       => 'tester@zalo.user',
            'firebase_id' => 'tester-zid',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);
    }

    public function test_seeder_creates_three_vouchers_with_expected_config(): void
    {
        $giam = Voucher::where('code', 'GIAM20K')->first();
        $this->assertNotNull($giam, 'GIAM20K phải được seed');
        $this->assertSame(Voucher::TYPE_FIXED, $giam->discount_type);
        $this->assertEquals(20000, (int) round((float) $giam->discount_value));
        $this->assertSame(100000, (int) $giam->min_order_amount);

        $this->assertNotNull(Voucher::where('code', 'SALE10')->first(), 'SALE10 phải được seed');
        $this->assertNotNull(Voucher::where('code', 'FREESHIP')->first(), 'FREESHIP phải được seed');
    }

    public function test_giam20k_blocks_order_below_min(): void
    {
        $giam = Voucher::where('code', 'GIAM20K')->first();

        // Tổng giỏ DƯỚI 100.000đ → bị chặn với message min-order.
        $res = $giam->isUsable($this->customer->id, 50000);
        $this->assertIsString($res);
        $this->assertStringContainsString('Đơn tối thiểu 100.000đ', $res);
    }

    public function test_giam20k_usable_at_or_above_min(): void
    {
        $giam = Voucher::where('code', 'GIAM20K')->first();

        // Đúng ngưỡng và trên ngưỡng → dùng được.
        $this->assertTrue($giam->isUsable($this->customer->id, 100000));
        $this->assertTrue($giam->isUsable($this->customer->id, 150000));
    }

    public function test_seeder_is_idempotent(): void
    {
        // Chạy lại không tạo bản ghi trùng / không lỗi unique code.
        $this->seed(VoucherSeeder::class);
        $this->assertSame(1, Voucher::where('code', 'GIAM20K')->count());
        $this->assertSame(3, Voucher::count());
    }
}
