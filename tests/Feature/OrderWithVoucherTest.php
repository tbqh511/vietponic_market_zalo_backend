<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Models\ZaloCategory;
use App\Models\ZaloOrder;
use App\Models\ZaloProduct;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderWithVoucherTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private ZaloProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);
        $this->product = ZaloProduct::create([
            'id'          => 1,
            'category_id' => $cat->id,
            'name'        => 'Rau muống',
            'price'       => 30000,
            'weight'      => 300,
            'stock'       => 100,
        ]);

        // Farm + batch để satisfy FEFO allocation trong store()
        $owner = Customer::create([
            'name'        => 'Farm Owner',
            'email'       => 'owner@zalo.user',
            'firebase_id' => 'farm-owner-zid',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);
        $farm = Farm::create([
            'name'              => 'Vietponics',
            'slug'              => 'vietponics',
            'code'              => 'VP01',
            'owner_customer_id' => $owner->id,
            'status'            => 'active',
        ]);
        FarmStockBatch::create([
            'farm_id'       => $farm->id,
            'product_id'    => $this->product->id,
            'batch_date'    => now()->toDateString(),
            'quantity_in'   => 100,
            'quantity_sold' => 0,
            'quantity_remaining' => 100,
            'cost_price'    => 20000,
            'status'        => 'active',
        ]);

        VtpProvince::create(['id' => 1, 'name' => 'TP.HCM', 'status' => 1]);
        VtpDistrict::create(['id' => 10, 'province_id' => 1, 'name' => 'Quận 1', 'status' => 1]);
        VtpWard::create(['id' => 100, 'district_id' => 10, 'name' => 'Phường Bến Nghé', 'status' => 1]);

        $this->customer = Customer::create([
            'name'        => 'Test User',
            'email'       => 'test@zalo.user',
            'firebase_id' => 'test-zalo-id',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($this->customer)];
    }

    private function payload(int $qty, int $shippingFee, int $total, ?string $voucherCode = null): array
    {
        $payload = [
            'items' => [[
                'product_id' => (string) $this->product->id,
                'name'       => $this->product->name,
                'price'      => (string) $this->product->price,
                'quantity'   => (string) $qty,
                'image'      => '',
                'detail'     => '',
            ]],
            'delivery' => [
                'type'          => 'shipping',
                'address'       => '123 Lý Tự Trọng',
                'name'          => 'Nguyễn Văn A',
                'phone'         => '0912345678',
                'province_id'   => 1,
                'district_id'   => 10,
                'ward_id'       => 100,
                'province_name' => 'TP.HCM',
                'district_name' => 'Quận 1',
                'ward_name'     => 'Phường Bến Nghé',
            ],
            'subtotal'              => (string) ($this->product->price * $qty),
            'shipping_fee'          => (string) $shippingFee,
            'shipping_service_code' => 'LCOD',
            'shipping_service_name' => 'Chuyển phát tiêu chuẩn',
            'total'                 => (string) $total,
            'note'                  => '',
            'created_at'            => now()->toIso8601String(),
        ];
        if ($voucherCode !== null) {
            $payload['voucher_code'] = $voucherCode;
        }
        return $payload;
    }

    public function test_order_with_percent_voucher_applies_discount(): void
    {
        Voucher::create([
            'code'                  => 'SALE10',
            'name'                  => 'Giảm 10%',
            'discount_type'         => Voucher::TYPE_PERCENT,
            'discount_value'        => 10,
            'max_discount_amount'   => 50000,
            'is_active'             => true,
            'is_public'             => true,
            'max_uses_per_customer' => 1,
        ]);

        // subtotal = 60.000; discount = 6.000; shipping = 30.000 → total = 84.000
        $res = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->payload(2, 30000, 84000, 'SALE10'));

        $res->assertCreated();
        $orderId = $res->json('orderId');

        $order = ZaloOrder::find($orderId);
        $this->assertSame('SALE10', $order->voucher_code);
        $this->assertSame(6000, (int) $order->discount_amount);
        $this->assertSame(84000, (int) $order->total);

        $voucher = Voucher::where('code', 'SALE10')->first();
        $this->assertSame(1, (int) $voucher->used_count);

        $this->assertSame(1, VoucherRedemption::where('order_id', $orderId)->count());
    }

    public function test_order_with_free_shipping_voucher(): void
    {
        Voucher::create([
            'code'           => 'FREESHIP',
            'name'           => 'Miễn ship',
            'discount_type'  => Voucher::TYPE_FREE_SHIPPING,
            'discount_value' => 0,
            'is_active'      => true,
            'is_public'      => true,
        ]);

        // subtotal = 60.000, shipping = 30.000, discount = 30.000 → total = 60.000
        $res = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->payload(2, 30000, 60000, 'FREESHIP'));

        $res->assertCreated();
        $order = ZaloOrder::find($res->json('orderId'));
        $this->assertSame(30000, (int) $order->discount_amount);
        $this->assertSame(60000, (int) $order->total);
    }

    public function test_order_rejected_when_voucher_invalid(): void
    {
        $res = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->payload(2, 30000, 84000, 'NOPE'));

        $res->assertStatus(422);
        $this->assertSame('voucher_invalid', $res->json('reason'));
    }

    public function test_order_rejected_when_total_mismatch_with_voucher(): void
    {
        Voucher::create([
            'code'           => 'GIAM20K',
            'name'           => 'Giảm 20k',
            'discount_type'  => Voucher::TYPE_FIXED,
            'discount_value' => 20000,
            'is_active'      => true,
            'is_public'      => true,
        ]);

        // subtotal 60k + ship 30k - giảm 20k = 70k đúng. Client gửi 80k (tamper).
        $res = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->payload(2, 30000, 80000, 'GIAM20K'));

        $res->assertStatus(422);
    }

    public function test_cancellation_releases_voucher(): void
    {
        $voucher = Voucher::create([
            'code'           => 'GIAM20K',
            'name'           => 'Giảm 20k',
            'discount_type'  => Voucher::TYPE_FIXED,
            'discount_value' => 20000,
            'is_active'      => true,
            'is_public'      => true,
        ]);

        $res = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->payload(2, 30000, 70000, 'GIAM20K'));
        $res->assertCreated();
        $orderId = $res->json('orderId');

        $this->assertSame(1, (int) $voucher->fresh()->used_count);

        $cancelRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$orderId}/cancel", [
                'reason_code' => 'customer_other',
            ]);
        $cancelRes->assertStatus(200);

        $this->assertSame(0, (int) $voucher->fresh()->used_count);
        $this->assertSame(0, VoucherRedemption::where('order_id', $orderId)->count());
    }
}
