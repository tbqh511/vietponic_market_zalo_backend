<?php

namespace Tests\Feature;

use App\Events\OrderPaymentSucceeded;
use App\Models\Customer;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

/**
 * ZaloNotifyTest — webhook POST /api/notify (notifySDK).
 *
 * Phủ B1 (ORDER-06):
 *   - Chặn default-success: thiếu/sai resultCode (đơn online) → KHÔNG set paid.
 *   - Race guard: notify paid đến SAU khi đơn đã huỷ → KHÔNG ghi đè cancelled→paid.
 * + Regression: success/failed/MAC-sai/COD.
 *
 * MAC webhook = HMAC-SHA256( ksort(data) → "k=v&...", ZALO_CHECK_OUT_SECRET ).
 */
class ZaloNotifyTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->makeCustomer();
    }

    private function signNotify(array $data): string
    {
        ksort($data);
        $raw = collect($data)->map(fn ($v, $k) => "{$k}={$v}")->implode('&');
        return hash_hmac('sha256', $raw, env('ZALO_CHECK_OUT_SECRET'));
    }

    private function makeOnlineOrder(string $sdkOrderId, array $overrides = []): ZaloOrder
    {
        return ZaloOrder::create(array_merge([
            'customer_id'           => $this->customer->id,
            'status'                => 'pending',
            'payment_status'        => 'pending',
            'payment_method'        => 'BANK_SANDBOX',
            'total'                 => 100000,
            'checkout_sdk_order_id' => $sdkOrderId,
            'created_at'            => now(),
        ], $overrides));
    }

    private function postNotify(array $data)
    {
        return $this->postJson('/api/notify', [
            'data'       => $data,
            'overallMac' => $this->signNotify($data),
        ]);
    }

    // ─── Regression: success / failed ─────────────────────────────────────

    public function test_online_result_code_1_marks_success_and_fires_event(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        $order = $this->makeOnlineOrder('sdk-notify-001');

        $this->postNotify([
            'appId'      => '1234567890',
            'orderId'    => 'sdk-notify-001',
            'method'     => 'BANK_SANDBOX',
            'resultCode' => 1,
        ])->assertOk()->assertJson(['returnCode' => 1]);

        $this->assertSame('success', $order->fresh()->payment_status);
        Event::assertDispatched(OrderPaymentSucceeded::class);
    }

    public function test_online_result_code_minus_1_marks_failed(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        $order = $this->makeOnlineOrder('sdk-notify-002');

        $this->postNotify([
            'appId'      => '1234567890',
            'orderId'    => 'sdk-notify-002',
            'method'     => 'BANK_SANDBOX',
            'resultCode' => -1,
        ])->assertOk();

        $this->assertSame('failed', $order->fresh()->payment_status);
        Event::assertNotDispatched(OrderPaymentSucceeded::class);
    }

    // ─── B1: chặn default-success ─────────────────────────────────────────

    public function test_online_missing_result_code_does_not_mark_paid(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        $order = $this->makeOnlineOrder('sdk-notify-003');

        // KHÔNG có resultCode trong data — MAC ký trên đúng tập field hiện có.
        $res = $this->postNotify([
            'appId'   => '1234567890',
            'orderId' => 'sdk-notify-003',
            'method'  => 'BANK_SANDBOX',
        ]);

        $res->assertOk()->assertJson(['returnCode' => 0]);
        $this->assertSame('pending', $order->fresh()->payment_status);
        Event::assertNotDispatched(OrderPaymentSucceeded::class);
    }

    public function test_online_non_numeric_result_code_does_not_mark_paid(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        $order = $this->makeOnlineOrder('sdk-notify-004');

        $res = $this->postNotify([
            'appId'      => '1234567890',
            'orderId'    => 'sdk-notify-004',
            'method'     => 'BANK_SANDBOX',
            'resultCode' => 'abc',
        ]);

        $res->assertOk()->assertJson(['returnCode' => 0]);
        $this->assertSame('pending', $order->fresh()->payment_status);
        Event::assertNotDispatched(OrderPaymentSucceeded::class);
    }

    // ─── B1: race guard — notify paid sau khi đã huỷ ──────────────────────

    public function test_payment_notify_for_cancelled_order_does_not_revive(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        // Đơn huỷ bởi khách giữ payment_status='pending' (cancelByCustomer không đụng
        // payment_status) → KHÔNG được dựa idempotency guard, phải nhờ race guard status.
        $order = $this->makeOnlineOrder('sdk-notify-005', [
            'status'         => 'cancelled',
            'payment_status' => 'pending',
            'cancelled_by'   => 'customer',
        ]);

        $this->postNotify([
            'appId'      => '1234567890',
            'orderId'    => 'sdk-notify-005',
            'method'     => 'BANK_SANDBOX',
            'resultCode' => 1,
        ])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNotSame('success', $fresh->payment_status);
        Event::assertNotDispatched(OrderPaymentSucceeded::class);
    }

    // ─── Regression: MAC sai / COD ────────────────────────────────────────

    public function test_invalid_mac_leaves_order_untouched(): void
    {
        $order = $this->makeOnlineOrder('sdk-notify-006');

        $res = $this->postJson('/api/notify', [
            'data' => [
                'appId'      => '1234567890',
                'orderId'    => 'sdk-notify-006',
                'method'     => 'BANK_SANDBOX',
                'resultCode' => 1,
            ],
            'overallMac' => 'deadbeef-not-a-valid-mac',
        ]);

        $res->assertOk()->assertJson(['returnCode' => 0]);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_cod_notify_keeps_cod_and_saves_method(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        $order = $this->makeOnlineOrder('sdk-notify-007', [
            'payment_status' => 'cod',
            'payment_method' => 'COD_SANDBOX',
        ]);

        $this->postNotify([
            'appId'      => '1234567890',
            'orderId'    => 'sdk-notify-007',
            'method'     => 'COD_SANDBOX',
            'resultCode' => 1,
        ])->assertOk()->assertJson(['returnCode' => 1]);

        $fresh = $order->fresh();
        $this->assertSame('cod', $fresh->payment_status);
        $this->assertSame('COD_SANDBOX', $fresh->payment_method);
        Event::assertNotDispatched(OrderPaymentSucceeded::class);
    }
}
