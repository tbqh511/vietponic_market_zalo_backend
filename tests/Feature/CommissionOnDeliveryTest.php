<?php

namespace Tests\Feature;

use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloOrder;
use App\Services\VtpWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

/**
 * AFF-03 (B2): Hoa hồng CTV tính theo đơn GIAO THÀNH CÔNG (status → delivered),
 * áp dụng MỌI payment method (gồm COD). Mốc ghi commission đã dời từ
 * OrderPaymentSucceeded (thanh toán) sang OrderDelivered (giao xong).
 *
 * Fire OrderDelivered tại 3 điểm: ZaloApiController::updateStatus (API admin),
 * ZaloOrderController::update (admin web), VtpWebhookService (webhook code 501).
 */
class CommissionOnDeliveryTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $referrer;
    private Customer $referred;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);

        $this->referrer = $this->makeApprovedAffiliate('REFDELIV1');
        $this->referred = $this->makeCustomer(['referred_by_customer_id' => $this->referrer->id]);
    }

    private function adminHeaders(): array
    {
        return ['X-Admin-Secret' => env('ADMIN_API_SECRET')];
    }

    private function makeOrder(array $overrides = []): ZaloOrder
    {
        return ZaloOrder::create(array_merge([
            'customer_id'    => $this->referred->id,
            'status'         => 'preparing',
            'payment_status' => 'cod',
            'payment_method' => 'COD',
            'total'          => 100000,
            'created_at'     => now(),
        ], $overrides));
    }

    /** Case 1 — COD có referral → delivered → sinh hoa hồng đúng số. */
    public function test_cod_order_delivered_creates_commission(): void
    {
        $order = $this->makeOrder(['payment_status' => 'cod', 'payment_method' => 'COD']);

        $res = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivering']);
        $res->assertOk();
        // Chưa delivered → chưa có commission.
        $this->assertSame(0, AffiliateCommission::where('order_id', $order->id)->count());

        $res = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered']);
        $res->assertOk();

        $this->assertSame(1, AffiliateCommission::where('order_id', $order->id)->count());
        $row = AffiliateCommission::where('order_id', $order->id)->first();
        $this->assertEquals(5000, $row->commission_amount); // 5% của 100000
        $this->assertSame('confirmed', $row->status);
        $this->assertEquals($this->referrer->id, $row->referrer_customer_id);
    }

    /** Case 2 — Online đã paid có referral → delivered → sinh hoa hồng (đúng 1 lần). */
    public function test_paid_online_order_delivered_creates_commission_once(): void
    {
        $order = $this->makeOrder([
            'payment_status' => 'success',
            'payment_method' => 'BANK',
            'status'         => 'preparing',
            'total'          => 200000,
        ]);

        $res = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered']);
        $res->assertOk();

        $this->assertSame(1, AffiliateCommission::where('order_id', $order->id)->count());
        $this->assertEquals(10000, AffiliateCommission::where('order_id', $order->id)->first()->commission_amount);
    }

    /** Case 3 — Delivered nhiều lần (idempotent PATCH delivered → delivered) → 1 row. */
    public function test_delivered_twice_is_idempotent(): void
    {
        $order = $this->makeOrder();

        $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered'])->assertOk();
        // Gửi lại delivered lần 2 (đã delivered) — không được sinh thêm row.
        $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered'])->assertOk();

        $this->assertSame(1, AffiliateCommission::where('order_id', $order->id)->count());
    }

    /** Case 4 — Đơn paid nhưng CHƯA delivered (webhook notify) → 0 commission. */
    public function test_paid_but_not_delivered_has_no_commission(): void
    {
        $secret = env('ZALO_CHECK_OUT_SECRET');
        $sdkOrderId = 'sdk-deliv-004';
        $order = $this->makeOrder([
            'payment_status'        => 'pending',
            'payment_method'        => 'BANK_SANDBOX',
            'status'                => 'pending',
            'checkout_sdk_order_id' => $sdkOrderId,
        ]);

        $data = [
            'appId'      => '1234567890',
            'orderId'    => $sdkOrderId,
            'method'     => 'BANK_SANDBOX',
            'resultCode' => 1,
        ];
        ksort($data);
        $raw = implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($data), array_values($data)));
        $mac = hash_hmac('sha256', $raw, $secret);

        $this->postJson('/api/notify', ['data' => $data, 'overallMac' => $mac])
            ->assertOk()->assertJson(['returnCode' => 1]);

        $order->refresh();
        $this->assertSame('success', $order->payment_status); // đã đánh dấu paid
        // Nhưng CHƯA giao → KHÔNG có hoa hồng (mốc đã dời sang delivered).
        $this->assertSame(0, AffiliateCommission::where('order_id', $order->id)->count());
    }

    /** Case 5 — Đơn không referral → delivered → không sinh gì. */
    public function test_no_referral_no_commission(): void
    {
        $standalone = $this->makeCustomer();
        $order = ZaloOrder::create([
            'customer_id'    => $standalone->id,
            'status'         => 'preparing',
            'payment_status' => 'cod',
            'payment_method' => 'COD',
            'total'          => 100000,
            'created_at'     => now(),
        ]);

        $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered'])->assertOk();

        $this->assertSame(0, AffiliateCommission::where('order_id', $order->id)->count());
    }

    /** Case 6 — VTP webhook code 501 → delivered → sinh hoa hồng (phủ điểm fire VtpWebhookService). */
    public function test_vtp_webhook_delivered_creates_commission(): void
    {
        $order = $this->makeOrder([
            'payment_status' => 'success',
            'payment_method' => 'BANK',
            'status'         => 'delivering',
            'total'          => 150000,
        ]);
        // Delivery tối thiểu để VtpWebhookService cập nhật snapshot + map status.
        $order->delivery()->create([
            'type'    => 'shipping',
            'address' => 'x',
            'name'    => 'x',
            'phone'   => '0900000000',
        ]);

        $service = app(VtpWebhookService::class);
        $service->processEvent($order->fresh()->load('delivery'), [
            'ORDER_STATUS'     => 501,
            'ORDER_STATUSDATE' => '10/06/2026 09:00:00',
            'ORDER_NUMBER'     => 'VTP-DELIV-006',
            'STATUS_NAME'      => 'Đã giao',
        ]);

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame(1, AffiliateCommission::where('order_id', $order->id)->count());
        $this->assertEquals(7500, AffiliateCommission::where('order_id', $order->id)->first()->commission_amount);
    }
}
