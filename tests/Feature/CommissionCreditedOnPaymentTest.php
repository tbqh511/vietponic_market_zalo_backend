<?php

namespace Tests\Feature;

use App\Events\OrderDelivered;
use App\Jobs\CheckPaymentStatus;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

/**
 * AFF-03 (B2): Mốc ghi hoa hồng đã dời từ thanh toán (OrderPaymentSucceeded)
 * sang giao thành công (OrderDelivered). File này verify rằng các đường THANH
 * TOÁN (webhook /notify, job poll) chỉ set payment_status=success và KHÔNG còn
 * sinh hoa hồng; hoa hồng chỉ phát sinh khi OrderDelivered fire.
 *
 * Việc sinh hoa hồng ở mốc delivered (qua API/admin/VTP) được phủ ở
 * CommissionOnDeliveryTest.
 */
class CommissionCreditedOnPaymentTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $referrer;
    private Customer $referred;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);

        $this->referrer = $this->makeApprovedAffiliate('REFP2345');
        $this->referred = $this->makeCustomer(['referred_by_customer_id' => $this->referrer->id]);
    }

    public function test_webhook_notify_marks_paid_but_does_not_credit_commission(): void
    {
        $secret = env('ZALO_CHECK_OUT_SECRET');
        $sdkOrderId = 'sdk-order-001';
        $order = ZaloOrder::create([
            'customer_id' => $this->referred->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 100000,
            'checkout_sdk_order_id' => $sdkOrderId,
            'created_at' => now(),
        ]);

        $data = [
            'appId' => '1234567890',
            'orderId' => $sdkOrderId,
            'method' => 'BANK_SANDBOX',
            'resultCode' => 1,
        ];
        ksort($data);
        $raw = implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($data), array_values($data)));
        $mac = hash_hmac('sha256', $raw, $secret);

        $response = $this->postJson('/api/notify', [
            'data' => $data,
            'overallMac' => $mac,
        ]);
        $response->assertOk()->assertJson(['returnCode' => 1]);

        $order->refresh();
        $this->assertEquals('success', $order->payment_status);
        // Mốc đã dời sang delivered — thanh toán KHÔNG còn sinh hoa hồng.
        $this->assertEquals(0, AffiliateCommission::where('order_id', $order->id)->count());
    }

    public function test_webhook_notify_cod_keeps_status_and_skips_commission(): void
    {
        $secret = env('ZALO_CHECK_OUT_SECRET');
        $sdkOrderId = 'sdk-order-cod-001';
        $order = ZaloOrder::create([
            'customer_id' => $this->referred->id,
            'status' => 'pending',
            'payment_status' => 'cod',
            'payment_method' => 'COD_SANDBOX',
            'total' => 100000,
            'checkout_sdk_order_id' => $sdkOrderId,
            'created_at' => now(),
        ]);

        $data = [
            'appId' => '1234567890',
            'orderId' => $sdkOrderId,
            'method' => 'COD_SANDBOX',
            'resultCode' => 1,
        ];
        ksort($data);
        $raw = implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($data), array_values($data)));
        $mac = hash_hmac('sha256', $raw, $secret);

        $response = $this->postJson('/api/notify', [
            'data' => $data,
            'overallMac' => $mac,
        ]);
        $response->assertOk()->assertJson(['returnCode' => 1]);

        $order->refresh();
        $this->assertEquals('cod', $order->payment_status);
        $this->assertEquals('COD_SANDBOX', $order->payment_method);
        $this->assertEquals(0, AffiliateCommission::where('order_id', $order->id)->count());
    }

    public function test_job_polling_path_marks_paid_but_does_not_credit_commission(): void
    {
        $sdkOrderId = 'sdk-order-002';
        $miniAppId = '1234567890';
        $order = ZaloOrder::create([
            'customer_id' => $this->referred->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 200000,
            'checkout_sdk_order_id' => $sdkOrderId,
            'created_at' => now(),
        ]);

        Http::fake([
            'payment-mini.zalo.me/*' => Http::response([
                'returnCode' => 1,
                'returnMessage' => 'OK',
            ], 200),
        ]);

        (new CheckPaymentStatus($order->id, $sdkOrderId, $miniAppId))->handle();

        $order->refresh();
        $this->assertEquals('success', $order->payment_status);
        $this->assertEquals(0, AffiliateCommission::where('order_id', $order->id)->count());
    }

    public function test_double_fire_delivered_creates_exactly_one_row(): void
    {
        $order = ZaloOrder::create([
            'customer_id' => $this->referred->id,
            'status' => 'delivered',
            'payment_status' => 'success',
            'total' => 50000,
            'checkout_sdk_order_id' => 'sdk-order-003',
            'created_at' => now(),
        ]);

        event(new OrderDelivered($order->id));
        event(new OrderDelivered($order->id));

        $this->assertEquals(1, AffiliateCommission::where('order_id', $order->id)->count());
    }

    public function test_no_commission_when_customer_not_referred(): void
    {
        $standalone = $this->makeCustomer();
        $order = ZaloOrder::create([
            'customer_id' => $standalone->id,
            'status' => 'delivered',
            'payment_status' => 'success',
            'total' => 100000,
            'created_at' => now(),
        ]);

        event(new OrderDelivered($order->id));
        $this->assertEquals(0, AffiliateCommission::where('order_id', $order->id)->count());
    }
}
