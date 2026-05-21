<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\Station;
use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Models\ZaloCategory;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Models\ZaloProduct;
use App\Services\StationPickerService;
use App\Services\ViettelPostService;
use App\Services\VtpOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Test luồng tạo đơn VTP sau khi user checkout. Mock ViettelPostService để không
 * gọi VTP API thật. Tập trung kiểm tra: pickup không gọi VTP, shipping gọi VTP
 * + lưu vtp_order_number, fail VTP không rollback order.
 */
class VtpCreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private ZaloProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);
        $this->product = ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Rau muống',
            'price' => 30000, 'weight' => 300, 'stock' => 100,
        ]);
        VtpProvince::create(['id' => 1, 'name' => 'HCM', 'status' => 1]);
        VtpDistrict::create(['id' => 10, 'province_id' => 1, 'name' => 'Q1', 'status' => 1]);
        VtpWard::create(['id' => 100, 'district_id' => 10, 'name' => 'P1', 'status' => 1]);

        // Trạm lấy hàng (cần thiết để VtpOrderService chọn sender)
        Station::create([
            'id' => 1, 'name' => 'Trạm Đà Lạt', 'image' => '',
            'address' => 'Đà Lạt', 'lat' => 11.94, 'lng' => 108.45,
            'vtp_province_id' => 1, 'vtp_district_id' => 10, 'vtp_ward_id' => 100,
            'vtp_address' => 'Trạm Đà Lạt - VTP',
        ]);

        $this->customer = Customer::create([
            'name' => 'T', 'email' => 't@x', 'firebase_id' => 'fb1',
            'logintype' => 'zalo', 'isActive' => 1,
        ]);

        // Farm + batch để StockService FEFO allocation tìm thấy stock — bằng không
        // POST /orders sẽ 422 với "không đủ số lượng tồn kho".
        $owner = Customer::create([
            'name' => 'Farm Owner', 'email' => 'owner@x',
            'firebase_id' => 'farm-owner', 'logintype' => 'zalo', 'isActive' => 1,
        ]);
        $farm = Farm::create([
            'name' => 'Vietponics', 'slug' => 'vietponics', 'code' => 'VP01',
            'owner_customer_id' => $owner->id, 'status' => 'active',
        ]);
        FarmStockBatch::create([
            'farm_id' => $farm->id, 'product_id' => $this->product->id,
            'batch_date' => now()->toDateString(),
            'quantity_in' => 100, 'quantity_sold' => 0, 'quantity_remaining' => 100,
            'cost_price' => 20000, 'status' => 'active',
        ]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($this->customer)];
    }

    private function payload(string $type = 'shipping'): array
    {
        $subtotal = 60000;
        $ship = 35000;
        $base = [
            'items' => [[
                'product_id' => '1', 'name' => 'Rau muống',
                'price' => '30000', 'quantity' => '2', 'image' => '', 'detail' => '',
            ]],
            'subtotal'              => (string) $subtotal,
            'shipping_fee'          => (string) ($type === 'shipping' ? $ship : 0),
            'shipping_service_code' => 'VHT',
            'shipping_service_name' => 'Tiêu chuẩn',
            'total'                 => (string) ($subtotal + ($type === 'shipping' ? $ship : 0)),
            'note'                  => '',
            'created_at'            => now()->toIso8601String(),
        ];

        if ($type === 'shipping') {
            $base['delivery'] = [
                'type' => 'shipping', 'address' => '123 Lý Tự Trọng',
                'name' => 'Nguyễn A', 'phone' => '0912',
                'province_id' => 1, 'district_id' => 10, 'ward_id' => 100,
                'province_name' => 'HCM', 'district_name' => 'Q1', 'ward_name' => 'P1',
            ];
        } else {
            $base['delivery'] = [
                'type' => 'pickup', 'address' => 'Cửa hàng',
                'name' => 'Nguyễn A', 'phone' => '0912',
            ];
        }
        return $base;
    }

    // ── Shipping → gọi VTP, lưu mã vận đơn ──────────────────────────────────

    public function test_shipping_order_calls_vtp_and_saves_order_number(): void
    {
        // Mock VTP service trả ORDER_NUMBER giả
        $mockVtp = Mockery::mock(ViettelPostService::class);
        $mockVtp->shouldReceive('createOrder')
            ->once()
            ->andReturn([
                'ORDER_NUMBER'   => 'VTP_FAKE_001',
                'MONEY_TOTAL'    => 95000,
                'EXCHANGE_WEIGHT'=> 600,
            ]);
        $this->app->instance(ViettelPostService::class, $mockVtp);

        $this->withHeaders($this->headers())
            ->postJson('/api/orders', $this->payload('shipping'))
            ->assertCreated();

        $delivery = ZaloDelivery::first();
        $this->assertSame('VTP_FAKE_001', $delivery->vtp_order_number);
        $this->assertSame('VPN' . ZaloOrder::first()->id, $delivery->vtp_order_reference);
        $this->assertSame('103', $delivery->vtp_status_code);
    }

    // ── Pickup → KHÔNG gọi VTP ──────────────────────────────────────────────

    public function test_pickup_order_does_not_call_vtp(): void
    {
        $mockVtp = Mockery::mock(ViettelPostService::class);
        $mockVtp->shouldNotReceive('createOrder');
        $this->app->instance(ViettelPostService::class, $mockVtp);

        $this->withHeaders($this->headers())
            ->postJson('/api/orders', $this->payload('pickup'))
            ->assertCreated();

        $delivery = ZaloDelivery::first();
        $this->assertNull($delivery->vtp_order_number);
    }

    // ── VTP fail → order vẫn tạo (không rollback) ───────────────────────────

    public function test_vtp_failure_does_not_rollback_order(): void
    {
        $mockVtp = Mockery::mock(ViettelPostService::class);
        $mockVtp->shouldReceive('createOrder')
            ->once()
            ->andThrow(new \RuntimeException('VTP API down'));
        $this->app->instance(ViettelPostService::class, $mockVtp);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/orders', $this->payload('shipping'));

        $response->assertCreated();
        $this->assertSame(1, ZaloOrder::count(), 'Order vẫn được tạo dù VTP fail');

        $delivery = ZaloDelivery::first();
        $this->assertNull($delivery->vtp_order_number, 'Không có vtp_order_number khi VTP fail');
    }

    // ── VtpOrderService trực tiếp: dispatchOrderToVtp throw nếu đã có mã ────

    public function test_dispatch_throws_if_order_already_has_vtp_number(): void
    {
        $mockVtp = Mockery::mock(ViettelPostService::class);
        $this->app->instance(ViettelPostService::class, $mockVtp);

        $order = ZaloOrder::create([
            'customer_id' => $this->customer->id, 'status' => 'pending', 'payment_status' => 'success',
            'created_at' => now(), 'received_at' => now()->addDays(3),
            'subtotal' => 60000, 'shipping_fee' => 35000, 'total' => 95000,
            'shipping_service_code' => 'VHT',
        ]);
        ZaloDelivery::create([
            'order_id' => $order->id, 'type' => 'shipping',
            'address' => '123', 'name' => 'A', 'phone' => '0912', 'province_id' => 1,
            'vtp_order_number' => 'VTP_EXISTS_001',
        ]);

        $svc = app(VtpOrderService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/đã có vtp_order_number/u');
        $svc->dispatchOrderToVtp($order);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
