<?php

namespace Tests\Feature;

use App\Jobs\CheckRefundStatus;
use App\Models\Customer;
use App\Models\ZaloOrder;
use App\Services\ZaloPayRefundClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

/**
 * CheckRefundStatusJobTest (B5 — ORDPRO-09).
 *
 * Job poll trạng thái refund ZaloPay cho đơn ở state 'processing':
 *   query→refunded → refunded + refunded_at
 *   query→failed   → failed + note
 *   query→processing & còn tries → re-dispatch (attempt+1)
 *   query→processing & hết tries → fallback pending_manual
 *   order không còn 'processing' → bail, KHÔNG query
 *
 * ZaloPayRefundClient truyền thẳng vào handle() → mock trực tiếp.
 */
class CheckRefundStatusJobTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->makeCustomer();
    }

    private function makeProcessingOrder(array $overrides = []): ZaloOrder
    {
        return ZaloOrder::create(array_merge([
            'customer_id'            => $this->customer->id,
            'status'                 => 'cancelled',
            'payment_status'         => 'success',
            'payment_method'         => 'ZALOPAY',
            'total'                  => '100000',
            'checkout_sdk_order_id'  => 'sdk-' . uniqid(),
            'refund_status'          => 'processing',
            'refund_transaction_id'  => 'refund_tx_' . uniqid(),
            'created_at'             => now(),
        ], $overrides));
    }

    public function test_query_refunded_marks_refunded(): void
    {
        $order = $this->makeProcessingOrder();
        $client = Mockery::mock(ZaloPayRefundClient::class);
        $client->shouldReceive('queryRefund')->once()->andReturn(['ok' => true, 'status' => 'refunded']);

        (new CheckRefundStatus($order->id, 0))->handle($client);

        $fresh = $order->fresh();
        $this->assertSame('refunded', $fresh->refund_status);
        $this->assertNotNull($fresh->refunded_at);
    }

    public function test_query_failed_marks_failed_with_note(): void
    {
        $order = $this->makeProcessingOrder();
        $client = Mockery::mock(ZaloPayRefundClient::class);
        $client->shouldReceive('queryRefund')->once()->andReturn(['ok' => false, 'status' => 'failed', 'message' => 'rejected']);

        (new CheckRefundStatus($order->id, 0))->handle($client);

        $fresh = $order->fresh();
        $this->assertSame('failed', $fresh->refund_status);
        $this->assertStringContainsString('rejected', (string) $fresh->refund_note);
    }

    public function test_still_processing_reschedules_when_tries_remain(): void
    {
        Queue::fake();
        $order = $this->makeProcessingOrder();
        $client = Mockery::mock(ZaloPayRefundClient::class);
        $client->shouldReceive('queryRefund')->once()->andReturn(['ok' => true, 'status' => 'processing']);

        (new CheckRefundStatus($order->id, 0))->handle($client);

        // Vẫn processing, chưa fallback; job được đặt lại để check tiếp.
        $this->assertSame('processing', $order->fresh()->refund_status);
        Queue::assertPushed(CheckRefundStatus::class);
    }

    public function test_still_processing_falls_back_to_manual_after_max_attempts(): void
    {
        Queue::fake();
        $order = $this->makeProcessingOrder();
        $client = Mockery::mock(ZaloPayRefundClient::class);
        $client->shouldReceive('queryRefund')->once()->andReturn(['ok' => true, 'status' => 'processing']);

        // attempt=2 là lần cuối → không re-dispatch, fallback pending_manual.
        (new CheckRefundStatus($order->id, 2))->handle($client);

        $this->assertSame('pending_manual', $order->fresh()->refund_status);
        Queue::assertNotPushed(CheckRefundStatus::class);
    }

    public function test_bails_when_order_no_longer_processing(): void
    {
        $order = $this->makeProcessingOrder(['refund_status' => 'refunded']);
        $client = Mockery::mock(ZaloPayRefundClient::class);
        $client->shouldNotReceive('queryRefund');

        (new CheckRefundStatus($order->id, 0))->handle($client);

        $this->assertSame('refunded', $order->fresh()->refund_status);
    }
}
