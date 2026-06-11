<?php

namespace Tests\Feature;

use App\Jobs\CheckRefundStatus;
use App\Models\Customer;
use App\Models\ZaloOrder;
use App\Services\RefundService;
use App\Services\ZaloPayRefundClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

/**
 * RefundServiceTest (B5 — ORDPRO-09 / ORDPRO-10 / ORDPRO-08 phần refund).
 *
 * Khoá nhánh refund theo payment_method khi đơn bị HUỶ:
 *   - ZALOPAY (đã trả) → gọi ZaloPayRefundClient: refunded / processing(+job) / fail→pending_manual
 *   - MOMO / BANK (đã trả) → pending_manual + refund_amount/method/note, KHÔNG gọi API refund
 *   - COD hoặc chưa thanh toán → not_required, KHÔNG gọi API refund (ORDPRO-08)
 *   - method lạ → pending_manual generic
 *   - idempotent: refund_status đã set → no-op
 *
 * ZaloPayRefundClient được mock qua container (constructor-inject vào RefundService).
 * KHÔNG đổi logic gọi API refund của Zalo (MAC/secret giữ nguyên) — chỉ thêm test.
 */
class RefundServiceTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->makeCustomer();
    }

    private function makeOrder(string $method, string $paymentStatus, array $overrides = []): ZaloOrder
    {
        return ZaloOrder::create(array_merge([
            'customer_id'           => $this->customer->id,
            'status'                => 'cancelled', // refund chạy SAU khi đã cancelled
            'payment_status'        => $paymentStatus,
            'payment_method'        => $method,
            'total'                 => '100000',
            'checkout_sdk_order_id' => 'sdk-' . uniqid(),
            'created_at'            => now(),
        ], $overrides));
    }

    private function makePaidOrder(string $method, array $overrides = []): ZaloOrder
    {
        return $this->makeOrder($method, 'success', $overrides);
    }

    /** Mock client KHÔNG được phép gọi requestRefund (COD/MoMo/Bank/method lạ/idempotent). */
    private function mockClientNeverCalled(): void
    {
        $this->mock(ZaloPayRefundClient::class, function ($m) {
            $m->shouldNotReceive('requestRefund');
        });
    }

    // ── ZALOPAY (đã trả) ───────────────────────────────────────────────────────

    public function test_zalopay_paid_refund_success_sets_refunded(): void
    {
        $this->mock(ZaloPayRefundClient::class, function ($m) {
            $m->shouldReceive('requestRefund')->once()->andReturn([
                'ok' => true, 'status' => 'refunded', 'providerId' => 'zp-refund-123',
            ]);
        });

        $order = $this->makePaidOrder('ZALOPAY');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $fresh = $order->fresh();
        $this->assertSame('refunded', $fresh->refund_status);
        $this->assertSame('zp-refund-123', $fresh->refund_provider_id);
        $this->assertNotNull($fresh->refunded_at);
    }

    public function test_zalopay_processing_dispatches_check_refund_job(): void
    {
        Queue::fake();
        $this->mock(ZaloPayRefundClient::class, function ($m) {
            $m->shouldReceive('requestRefund')->once()->andReturn([
                'ok' => true, 'status' => 'processing', 'providerId' => 'zp-proc-1',
            ]);
        });

        $order = $this->makePaidOrder('ZALOPAY');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $this->assertSame('processing', $order->fresh()->refund_status);
        Queue::assertPushed(CheckRefundStatus::class);
    }

    public function test_zalopay_refund_failure_falls_back_to_pending_manual(): void
    {
        $this->mock(ZaloPayRefundClient::class, function ($m) {
            $m->shouldReceive('requestRefund')->once()->andReturn([
                'ok' => false, 'status' => 'failed', 'message' => 'HTTP 500',
            ]);
        });

        $order = $this->makePaidOrder('ZALOPAY');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $fresh = $order->fresh();
        $this->assertSame('pending_manual', $fresh->refund_status);
        $this->assertStringContainsString('HTTP 500', (string) $fresh->refund_note);
    }

    // ── MOMO / BANK (đã trả) → manual, KHÔNG gọi API ─────────────────────────────

    public function test_momo_paid_goes_to_pending_manual_without_api_call(): void
    {
        $this->mockClientNeverCalled();

        $order = $this->makePaidOrder('MOMO');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $fresh = $order->fresh();
        $this->assertSame('pending_manual', $fresh->refund_status);
        $this->assertEquals(100000, (float) $fresh->refund_amount);
        $this->assertSame('MOMO', $fresh->refund_method);
        $this->assertNotEmpty($fresh->refund_note);
    }

    public function test_bank_paid_goes_to_pending_manual_without_api_call(): void
    {
        $this->mockClientNeverCalled();

        $order = $this->makePaidOrder('BANK');
        app(RefundService::class)->processCancellationRefund($order, 'admin');

        $fresh = $order->fresh();
        $this->assertSame('pending_manual', $fresh->refund_status);
        $this->assertEquals(100000, (float) $fresh->refund_amount);
        $this->assertSame('BANK', $fresh->refund_method);
        $this->assertNotEmpty($fresh->refund_note);
    }

    // ── COD / chưa thanh toán → not_required, KHÔNG gọi API (ORDPRO-08) ──────────

    public function test_cod_unpaid_sets_not_required_without_api_call(): void
    {
        $this->mockClientNeverCalled();

        $order = $this->makeOrder('COD', 'cod');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $this->assertSame('not_required', $order->fresh()->refund_status);
    }

    public function test_cod_method_even_if_paid_is_not_required(): void
    {
        $this->mockClientNeverCalled();

        // payment_status='success' nhưng method bắt đầu bằng COD → vẫn not_required.
        $order = $this->makeOrder('COD_SANDBOX', 'success');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $this->assertSame('not_required', $order->fresh()->refund_status);
    }

    public function test_unpaid_online_order_is_not_required_without_api_call(): void
    {
        $this->mockClientNeverCalled();

        // Đơn online (BANK) nhưng chưa thanh toán thành công → không cần hoàn.
        $order = $this->makeOrder('BANK', 'pending');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $this->assertSame('not_required', $order->fresh()->refund_status);
    }

    // ── Method lạ + idempotency ─────────────────────────────────────────────────

    public function test_unknown_method_paid_goes_to_pending_manual(): void
    {
        $this->mockClientNeverCalled();

        $order = $this->makePaidOrder('CRYPTO');
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        $fresh = $order->fresh();
        $this->assertSame('pending_manual', $fresh->refund_status);
        $this->assertSame('CRYPTO', $fresh->refund_method);
    }

    public function test_idempotent_when_refund_status_already_set(): void
    {
        $this->mockClientNeverCalled();

        $order = $this->makePaidOrder('ZALOPAY', ['refund_status' => 'refunded']);
        app(RefundService::class)->processCancellationRefund($order, 'customer');

        // Không bị làm lại / không gọi API.
        $this->assertSame('refunded', $order->fresh()->refund_status);
    }
}
