<?php

namespace Tests\Feature;

use App\Events\OrderPaymentSucceeded;
use App\Models\Customer;
use App\Models\Station;
use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Models\ZaloCategory;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Services\ViettelPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Test listener CreateVtpOrderOnPayment (ORDER-16, B16).
 *
 * Phủ các nhánh guard của listener mà VtpCreateOrderTest KHÔNG test:
 * - Đơn BANK/MOMO shipping → fire OrderPaymentSucceeded → VTP tạo qua listener
 * - Đơn pickup → KHÔNG tạo VTP (guard :44-46)
 * - Đơn COD → KHÔNG tạo VTP (guard :39-41, VTP đã tạo inline trong store())
 * - Idempotent: đã có vtp_order_number → skip (guard :51-53)
 * - VTP service fail → listener bắt exception, không throw (catch :62-71)
 *
 * Chú ý B2: RecordAffiliateCommission dùng OrderDelivered (tách biệt),
 * listener VTP vẫn đúng mốc OrderPaymentSucceeded — không xung đột.
 */
class CreateVtpOrderOnPaymentListenerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private ZaloProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);
        $this->product = ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Rau muống',
            'price' => 30000, 'weight' => 300, 'stock' => 100,
        ]);
        VtpProvince::create(['id' => 1, 'name' => 'HCM', 'status' => 1]);
        VtpDistrict::create(['id' => 10, 'province_id' => 1, 'name' => 'Q1', 'status' => 1]);
        VtpWard::create(['id' => 100, 'district_id' => 10, 'name' => 'P1', 'status' => 1]);
        Station::create([
            'id' => 1, 'name' => 'Trạm Đà Lạt', 'image' => '',
            'address' => 'Đà Lạt', 'lat' => 11.94, 'lng' => 108.45,
            'vtp_province_id' => 1, 'vtp_district_id' => 10, 'vtp_ward_id' => 100,
            'vtp_address' => 'Trạm Đà Lạt - VTP',
        ]);
        $this->customer = Customer::create([
            'name' => 'Khách Test', 'email' => 'kt@x', 'firebase_id' => 'fb_test',
            'logintype' => 'zalo', 'isActive' => 1,
        ]);
    }

    /** Tạo ZaloOrder + ZaloOrderItem + ZaloDelivery trực tiếp trong DB. */
    private function makeOrder(
        string $paymentMethod,
        string $deliveryType,
        ?string $existingVtpNumber = null,
    ): ZaloOrder {
        $isShipping = $deliveryType === 'shipping';
        $order = ZaloOrder::create([
            'customer_id'          => $this->customer->id,
            'status'               => 'pending',
            'payment_status'       => 'success',
            'payment_method'       => $paymentMethod,
            'subtotal'             => 60000,
            'shipping_fee'         => $isShipping ? 35000 : 0,
            'total'                => $isShipping ? 95000 : 60000,
            'shipping_service_code'=> 'VHT',
            'created_at'           => now(),
            'received_at'          => now()->addDays(3),
        ]);

        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'name'       => $this->product->name,
            'price'      => 30000,
            'quantity'   => 2,
            'image'      => '',
            'detail'     => '',
        ]);

        $deliveryData = [
            'order_id' => $order->id,
            'type'     => $deliveryType,
            'address'  => '123 Lý Tự Trọng',
            'name'     => 'Nguyễn A',
            'phone'    => '0912000000',
        ];
        if ($isShipping) {
            $deliveryData += [
                'province_id'   => 1,
                'district_id'   => 10,
                'ward_id'       => 100,
                'province_name' => 'HCM',
                'district_name' => 'Q1',
                'ward_name'     => 'P1',
            ];
        }
        if ($existingVtpNumber !== null) {
            $deliveryData['vtp_order_number'] = $existingVtpNumber;
        }
        ZaloDelivery::create($deliveryData);

        return $order;
    }

    private function mockVtpReturns(string $orderNumber): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldReceive('createOrder')
            ->once()
            ->andReturn([
                'ORDER_NUMBER'    => $orderNumber,
                'MONEY_TOTAL'     => 95000,
                'EXCHANGE_WEIGHT' => 600,
            ]);
        $this->app->instance(ViettelPostService::class, $mock);
    }

    private function mockVtpNotCalled(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldNotReceive('createOrder');
        $this->app->instance(ViettelPostService::class, $mock);
    }

    // ── Shipping BANK → listener tạo đơn VTP ────────────────────────────────

    public function test_bank_shipping_triggers_vtp_creation(): void
    {
        $this->mockVtpReturns('VTP_BANK_001');
        $order = $this->makeOrder('BANK', 'shipping');

        event(new OrderPaymentSucceeded($order->id));

        $delivery = ZaloDelivery::where('order_id', $order->id)->first();
        $this->assertSame('VTP_BANK_001', $delivery->vtp_order_number);
        $this->assertSame('VPN' . $order->id, $delivery->vtp_order_reference);
        $this->assertSame('103', $delivery->vtp_status_code);
    }

    // ── Shipping MOMO → listener tạo đơn VTP ────────────────────────────────

    public function test_momo_shipping_triggers_vtp_creation(): void
    {
        $this->mockVtpReturns('VTP_MOMO_001');
        $order = $this->makeOrder('MOMO', 'shipping');

        event(new OrderPaymentSucceeded($order->id));

        $delivery = ZaloDelivery::where('order_id', $order->id)->first();
        $this->assertSame('VTP_MOMO_001', $delivery->vtp_order_number);
    }

    // ── Pickup → listener KHÔNG tạo VTP (guard :44-46) ──────────────────────

    public function test_pickup_order_does_not_create_vtp(): void
    {
        $this->mockVtpNotCalled();
        $order = $this->makeOrder('BANK', 'pickup');

        event(new OrderPaymentSucceeded($order->id));

        $delivery = ZaloDelivery::where('order_id', $order->id)->first();
        $this->assertNull($delivery->vtp_order_number);
    }

    // ── COD → listener skip (guard :39-41, VTP đã tạo inline trong store()) ─

    public function test_cod_order_does_not_create_vtp(): void
    {
        $this->mockVtpNotCalled();
        $order = $this->makeOrder('COD_SANDBOX', 'shipping');

        event(new OrderPaymentSucceeded($order->id));

        $delivery = ZaloDelivery::where('order_id', $order->id)->first();
        $this->assertNull($delivery->vtp_order_number);
    }

    // ── Idempotent: đã có mã vận đơn → KHÔNG tạo lại (guard :51-53) ─────────

    public function test_idempotent_guard_skips_if_vtp_number_exists(): void
    {
        $this->mockVtpNotCalled();
        $order = $this->makeOrder('BANK', 'shipping', 'VTP_EXISTING_001');

        event(new OrderPaymentSucceeded($order->id));

        $delivery = ZaloDelivery::where('order_id', $order->id)->first();
        $this->assertSame('VTP_EXISTING_001', $delivery->vtp_order_number, 'Mã vận đơn cũ không bị ghi đè.');
    }

    // ── VTP service fail → listener bắt exception, KHÔNG throw (catch :62-71) ─

    public function test_listener_does_not_throw_when_vtp_fails(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldReceive('createOrder')
            ->once()
            ->andThrow(new \RuntimeException('VTP API down'));
        $this->app->instance(ViettelPostService::class, $mock);

        $order = $this->makeOrder('BANK', 'shipping');

        event(new OrderPaymentSucceeded($order->id));

        $delivery = ZaloDelivery::where('order_id', $order->id)->first();
        $this->assertNull($delivery->vtp_order_number, 'VTP fail → không lưu vtp_order_number.');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
