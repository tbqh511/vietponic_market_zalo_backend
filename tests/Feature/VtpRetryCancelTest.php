<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Services\ViettelPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Test cron vtp:retry-cancel: retry các đơn đã cancel local nhưng VTP
 * cancel chưa xác nhận. Mock ViettelPostService để không gọi API thật.
 */
class VtpRetryCancelTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create([
            'name' => 'T', 'email' => 't@x', 'firebase_id' => 'fb',
            'logintype' => 'zalo', 'isActive' => 1,
        ]);
    }

    private function makeDelivery(array $attrs = []): array
    {
        $order = ZaloOrder::create([
            'customer_id' => $this->customer->id,
            'status' => 'cancelled',
            'payment_status' => 'pending',
            'cancellation_reason' => 'Test',
            'created_at' => now(),
            'total' => 100000,
        ]);
        $delivery = ZaloDelivery::create(array_merge([
            'order_id'                => $order->id,
            'type'                    => 'shipping',
            'address'                 => '123',
            'name'                    => 'A',
            'phone'                   => '0912',
            'vtp_order_number'        => 'VTP_TEST_' . $order->id,
            'vtp_status_code'         => '300',
            'vtp_cancel_requested_at' => now()->subHours(2),
            'vtp_cancel_attempts'     => 1,
            'vtp_cancel_failed_at'    => now()->subHours(2),
            'vtp_cancel_last_error'   => 'previous failure',
        ], $attrs));
        return ['order' => $order, 'delivery' => $delivery];
    }

    public function test_retry_succeeds_marks_delivery_as_cancelled(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldReceive('cancelOrder')->once()->andReturn([]);
        $this->app->instance(ViettelPostService::class, $mock);

        ['delivery' => $delivery] = $this->makeDelivery();

        $this->artisan('vtp:retry-cancel')->assertSuccessful();

        $fresh = $delivery->fresh();
        $this->assertSame('105', $fresh->vtp_status_code);
        $this->assertSame(2, $fresh->vtp_cancel_attempts);
        $this->assertNull($fresh->vtp_cancel_failed_at);
        $this->assertNull($fresh->vtp_cancel_last_error);
    }

    public function test_retry_failure_increments_attempts_and_logs_error(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldReceive('cancelOrder')->once()->andThrow(new \RuntimeException('VTP down'));
        $this->app->instance(ViettelPostService::class, $mock);

        ['delivery' => $delivery] = $this->makeDelivery();

        $this->artisan('vtp:retry-cancel')->assertSuccessful();

        $fresh = $delivery->fresh();
        $this->assertSame('300', $fresh->vtp_status_code);
        $this->assertSame(2, $fresh->vtp_cancel_attempts);
        $this->assertNotNull($fresh->vtp_cancel_failed_at);
        $this->assertSame('VTP down', $fresh->vtp_cancel_last_error);
    }

    public function test_skips_when_attempts_reached_max(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldNotReceive('cancelOrder');
        $this->app->instance(ViettelPostService::class, $mock);

        $this->makeDelivery(['vtp_cancel_attempts' => 5]);

        $this->artisan('vtp:retry-cancel')->assertSuccessful();
    }

    public function test_skips_when_status_in_terminal_cancel(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldNotReceive('cancelOrder');
        $this->app->instance(ViettelPostService::class, $mock);

        $this->makeDelivery(['vtp_status_code' => '105']);

        $this->artisan('vtp:retry-cancel')->assertSuccessful();
    }

    public function test_skips_when_no_cancel_requested(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldNotReceive('cancelOrder');
        $this->app->instance(ViettelPostService::class, $mock);

        $this->makeDelivery(['vtp_cancel_requested_at' => null]);

        $this->artisan('vtp:retry-cancel')->assertSuccessful();
    }

    public function test_backoff_skips_recently_failed(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldNotReceive('cancelOrder');
        $this->app->instance(ViettelPostService::class, $mock);

        // Fail 10 phút trước — trong cửa sổ backoff 60 phút.
        $this->makeDelivery(['vtp_cancel_failed_at' => now()->subMinutes(10)]);

        $this->artisan('vtp:retry-cancel')->assertSuccessful();
    }

    public function test_targeted_order_id_bypasses_backoff(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldReceive('cancelOrder')->once()->andReturn([]);
        $this->app->instance(ViettelPostService::class, $mock);

        // Fail vừa xong — bình thường sẽ skip vì backoff, nhưng targeted bypass.
        ['order' => $order] = $this->makeDelivery(['vtp_cancel_failed_at' => now()]);

        $this->artisan('vtp:retry-cancel', ['order_id' => $order->id])->assertSuccessful();
    }

    public function test_dry_run_does_not_call_vtp(): void
    {
        $mock = Mockery::mock(ViettelPostService::class);
        $mock->shouldNotReceive('cancelOrder');
        $this->app->instance(ViettelPostService::class, $mock);

        $this->makeDelivery();

        $this->artisan('vtp:retry-cancel', ['--dry-run' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
