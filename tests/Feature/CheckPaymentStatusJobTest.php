<?php

namespace Tests\Feature;

use App\Events\OrderPaymentSucceeded;
use App\Jobs\CheckPaymentStatus;
use App\Models\Customer;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

/**
 * CheckPaymentStatusJobTest — job poll get-status từ Zalo.
 *   returnCode==1 → success + event; ==-1 → failed; khác → giữ pending + reschedule.
 *   B1: đơn đã 'cancelled' → job bail, KHÔNG poll / KHÔNG hồi sinh.
 */
class CheckPaymentStatusJobTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $customer;
    private string $miniAppId = '1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->makeCustomer();
    }

    private function makeOrder(string $sdkOrderId, array $overrides = []): ZaloOrder
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

    public function test_return_code_1_marks_success_and_fires_event(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        $order = $this->makeOrder('sdk-job-001');
        Http::fake(['payment-mini.zalo.me/*' => Http::response(['returnCode' => 1], 200)]);

        (new CheckPaymentStatus($order->id, 'sdk-job-001', $this->miniAppId))->handle();

        $this->assertSame('success', $order->fresh()->payment_status);
        Event::assertDispatched(OrderPaymentSucceeded::class);
    }

    public function test_return_code_minus_1_marks_failed(): void
    {
        $order = $this->makeOrder('sdk-job-002');
        Http::fake(['payment-mini.zalo.me/*' => Http::response(['returnCode' => -1], 200)]);

        (new CheckPaymentStatus($order->id, 'sdk-job-002', $this->miniAppId))->handle();

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_unknown_return_code_keeps_pending_and_reschedules(): void
    {
        Queue::fake();
        $order = $this->makeOrder('sdk-job-003');
        Http::fake(['payment-mini.zalo.me/*' => Http::response(['returnCode' => -2101], 200)]);

        (new CheckPaymentStatus($order->id, 'sdk-job-003', $this->miniAppId))->handle();

        $this->assertSame('pending', $order->fresh()->payment_status);
        Queue::assertPushed(CheckPaymentStatus::class);
    }

    public function test_cancelled_order_is_not_revived_by_poll(): void
    {
        Event::fake([OrderPaymentSucceeded::class]);
        // payment_status vẫn 'pending' nhưng đơn đã huỷ → guard status phải chặn.
        $order = $this->makeOrder('sdk-job-004', ['status' => 'cancelled']);
        Http::fake(['payment-mini.zalo.me/*' => Http::response(['returnCode' => 1], 200)]);

        (new CheckPaymentStatus($order->id, 'sdk-job-004', $this->miniAppId))->handle();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('pending', $fresh->payment_status);
        Http::assertNothingSent();
        Event::assertNotDispatched(OrderPaymentSucceeded::class);
    }
}
