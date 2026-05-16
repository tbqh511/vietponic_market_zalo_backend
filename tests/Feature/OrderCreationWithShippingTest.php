<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Models\ZaloCategory;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Models\ZaloProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderCreationWithShippingTest extends TestCase
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

    private function orderPayload(array $override = []): array
    {
        $subtotal    = $this->product->price * 2;
        $shippingFee = 35000;
        $total       = $subtotal + $shippingFee;

        return array_replace_recursive([
            'items' => [[
                'product_id' => (string) $this->product->id,
                'name'       => $this->product->name,
                'price'      => (string) $this->product->price,
                'quantity'   => '2',
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
            'subtotal'              => (string) $subtotal,
            'shipping_fee'          => (string) $shippingFee,
            'shipping_service_code' => 'LCOD',
            'shipping_service_name' => 'Chuyển phát tiêu chuẩn',
            'total'                 => (string) $total,
            'note'                  => '',
            'created_at'            => now()->toIso8601String(),
        ], $override);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_order_created_with_shipping_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload());

        $response->assertCreated();

        $order = ZaloOrder::first();
        $this->assertNotNull($order);
        $this->assertSame(35000, (int) $order->shipping_fee);
        $this->assertSame(60000, (int) $order->subtotal);
        $this->assertSame(95000, (int) $order->total);
        $this->assertSame('LCOD', $order->shipping_service_code);
    }

    public function test_delivery_stores_vtp_address_ids(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload());

        $delivery = ZaloDelivery::first();
        $this->assertSame(1, (int) $delivery->province_id);
        $this->assertSame(10, (int) $delivery->district_id);
        $this->assertSame(100, (int) $delivery->ward_id);
        $this->assertSame('TP.HCM', $delivery->province_name);
    }

    // ── Total tamper protection ───────────────────────────────────────────────

    public function test_order_rejected_when_total_tampered(): void
    {
        // Client gửi total thấp hơn 5000đ so với subtotal + shipping
        $subtotal    = $this->product->price * 2; // 60000
        $shippingFee = 35000;
        $tampered    = $subtotal + $shippingFee - 5000; // 90000 thay vì 95000

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload([
                'total' => (string) $tampered,
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error', true);
    }

    public function test_order_accepted_when_total_within_1000_tolerance(): void
    {
        // Sai lệch nhỏ (ví dụ rounding) ≤ 1000đ → chấp nhận
        $subtotal    = $this->product->price * 2;
        $shippingFee = 35000;
        $offByFew    = $subtotal + $shippingFee - 500;

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload([
                'total' => (string) $offByFew,
            ]));

        $response->assertCreated();
    }

    // ── Zero shipping (pickup mode) ───────────────────────────────────────────

    public function test_pickup_order_with_zero_shipping_fee(): void
    {
        $subtotal = $this->product->price * 1;

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [[
                    'product_id' => (string) $this->product->id,
                    'name'       => $this->product->name,
                    'price'      => (string) $this->product->price,
                    'quantity'   => '1',
                    'image'      => '',
                    'detail'     => '',
                ]],
                'delivery' => [
                    'type'    => 'pickup',
                    'address' => 'Cửa hàng chính',
                    'name'    => 'Nguyễn Văn A',
                    'phone'   => '0912345678',
                ],
                'subtotal'    => (string) $subtotal,
                'shipping_fee'=> '0',
                'total'       => (string) $subtotal,
                'note'        => '',
                'created_at'  => now()->toIso8601String(),
            ]);

        $response->assertCreated();
        $this->assertSame(0, (int) ZaloOrder::first()->shipping_fee);
    }
}
