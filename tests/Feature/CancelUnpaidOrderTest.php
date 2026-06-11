<?php

namespace Tests\Feature;

use App\Events\OrderPaymentSucceeded;
use App\Jobs\CancelUnpaidOrder;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\AffiliateCustomerFactory;
use Tests\BuildsReservedOrderFactory;
use Tests\TestCase;

/**
 * CancelUnpaidOrderTest — job tự huỷ đơn online quá hạn chưa thanh toán (ORDER-06).
 *   - pending/pending quá hạn → cancelled + hoàn kho.
 *   - paid / COD → KHÔNG đụng.
 *   - (Q1) poll Zalo trước khi huỷ: returnCode=1 → cứu đơn (success); -1/fail → huỷ.
 */
class CancelUnpaidOrderTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory, BuildsReservedOrderFactory;

    public function test_cancels_unpaid_online_order_and_releases_stock(): void
    {
        // online pending/pending, đã reserve 4 → batch.quantity_sold=4.
        [$order, $batch] = $this->makeReservedOrder([
            'status'         => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'BANK_SANDBOX',
        ], orderQty: 4, batchQty: 10);
        $this->assertEquals('4.00', $batch->fresh()->quantity_sold);

        (new CancelUnpaidOrder($order->id))->handle();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('failed', $fresh->payment_status);
        $this->assertSame('system', $fresh->cancelled_by);
        $this->assertNotNull($fresh->cancelled_at);
        // Kho hoàn về 0.
        $this->assertEquals('0.00', $batch->fresh()->quantity_sold);
    }

    public function test_does_not_touch_paid_order(): void
    {
        [$order, $batch] = $this->makeReservedOrder([
            'status'         => 'pending',
            'payment_status' => 'success',
            'payment_method' => 'BANK_SANDBOX',
        ], orderQty: 4, batchQty: 10);

        (new CancelUnpaidOrder($order->id))->handle();

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame('success', $fresh->payment_status);
        $this->assertEquals('4.00', $batch->fresh()->quantity_sold); // kho giữ nguyên
    }

    public function test_does_not_touch_cod_order(): void
    {
        [$order, $batch] = $this->makeReservedOrder([
            'status'         => 'pending',
            'payment_status' => 'cod',
            'payment_method' => 'COD',
        ], orderQty: 4, batchQty: 10);

        (new CancelUnpaidOrder($order->id))->handle();

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame('cod', $fresh->payment_status);
        $this->assertEquals('4.00', $batch->fresh()->quantity_sold);
    }

    public function test_poll_returns_paid_saves_order_and_does_not_cancel(): void
    {
        config(['services.zalo.mini_app_id' => '1234567890']);
        Event::fake([OrderPaymentSucceeded::class]);
        Http::fake(['payment-mini.zalo.me/*' => Http::response(['returnCode' => 1], 200)]);

        [$order, $batch] = $this->makeReservedOrder([
            'status'                => 'pending',
            'payment_status'        => 'pending',
            'payment_method'        => 'BANK_SANDBOX',
            'checkout_sdk_order_id' => 'sdk-cancel-poll-1',
        ], orderQty: 4, batchQty: 10);

        (new CancelUnpaidOrder($order->id))->handle();

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);          // KHÔNG huỷ
        $this->assertSame('success', $fresh->payment_status);  // được cứu
        $this->assertEquals('4.00', $batch->fresh()->quantity_sold); // kho giữ nguyên
        Event::assertDispatched(OrderPaymentSucceeded::class);
    }

    public function test_poll_returns_failed_cancels_and_releases(): void
    {
        config(['services.zalo.mini_app_id' => '1234567890']);
        Http::fake(['payment-mini.zalo.me/*' => Http::response(['returnCode' => -1], 200)]);

        [$order, $batch] = $this->makeReservedOrder([
            'status'                => 'pending',
            'payment_status'        => 'pending',
            'payment_method'        => 'BANK_SANDBOX',
            'checkout_sdk_order_id' => 'sdk-cancel-poll-2',
        ], orderQty: 4, batchQty: 10);

        (new CancelUnpaidOrder($order->id))->handle();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertEquals('0.00', $batch->fresh()->quantity_sold);
    }

    public function test_idempotent_on_already_cancelled_order(): void
    {
        [$order, $batch] = $this->makeReservedOrder([
            'status'         => 'cancelled',
            'payment_status' => 'failed',
            'payment_method' => 'BANK_SANDBOX',
        ], orderQty: 4, batchQty: 10);

        // Không throw, không đổi gì thêm (job bail ở guard status).
        (new CancelUnpaidOrder($order->id))->handle();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertEquals('4.00', $batch->fresh()->quantity_sold);
    }
}
