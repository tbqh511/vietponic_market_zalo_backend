<?php

namespace Tests\Feature;

use App\Jobs\CheckRefundStatus;
use App\Models\Customer;
use App\Models\StockMovement;
use App\Models\ZaloCategory;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Services\ZaloPayRefundClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CustomerCancelOrderTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $customer;
    private ZaloCategory $category;
    private ZaloProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);
        $this->product  = ZaloProduct::create([
            'id'             => 1,
            'category_id'    => $this->category->id,
            'name'           => 'Rau muống',
            'price'          => 20000,
            'stock'          => 50,
            'stock_reserved' => 0,
            'reorder_point'  => 5,
        ]);
        $this->customer = $this->makeCustomer();
    }

    private function authHeaders(?Customer $customer = null): array
    {
        $token = JWTAuth::fromUser($customer ?? $this->customer);
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function makeOrder(array $overrides = []): ZaloOrder
    {
        $order = ZaloOrder::create(array_merge([
            'customer_id'    => $this->customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'COD_SANDBOX',
            'total'          => 40000,
            'created_at'     => now(),
        ], $overrides));
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'name'       => $this->product->name,
            'price'      => $this->product->price,
            'quantity'   => 2,
        ]);
        $this->product->update(['stock_reserved' => 2]);
        return $order;
    }

    // ─── Happy paths per payment method ─────────────────────────────────────

    public function test_customer_can_cancel_pending_cod_order_no_refund_required(): void
    {
        $order = $this->makeOrder(['payment_method' => 'COD_SANDBOX', 'payment_status' => 'pending']);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item', 'reason' => 'Đặt nhầm'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'not_required')
            ->assertJsonPath('data.cancelled_by', 'customer');

        $this->product->refresh();
        $this->assertEquals(0, $this->product->stock_reserved);
        $this->assertDatabaseHas('stock_movements', [
            'order_id'      => $order->id,
            'movement_type' => 'unreserved',
        ]);
    }

    public function test_customer_cancel_paid_zalopay_order_triggers_refund_processing(): void
    {
        // Mock client để không gọi HTTP thật + return 'processing'
        $mock = Mockery::mock(ZaloPayRefundClient::class);
        $mock->shouldReceive('requestRefund')
            ->once()
            ->andReturn(['ok' => true, 'status' => 'processing', 'providerId' => 'ZP_REF_123']);
        $this->app->instance(ZaloPayRefundClient::class, $mock);

        Bus::fake([CheckRefundStatus::class]);

        $order = $this->makeOrder([
            'payment_method'        => 'ZALOPAY_SANDBOX',
            'payment_status'        => 'success',
            'checkout_sdk_order_id' => 'ZALOTRANS_ABC',
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'no_longer_needed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'processing')
            ->assertJsonPath('data.refund_provider_id', 'ZP_REF_123');

        Bus::assertDispatched(CheckRefundStatus::class);
    }

    public function test_customer_cancel_paid_bank_order_marks_pending_manual(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'BANK_SANDBOX',
            'payment_status' => 'success',
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'address_change'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'pending_manual')
            ->assertJsonPath('data.refund_amount', '40000.00');
    }

    public function test_customer_cancel_paid_momo_order_marks_pending_manual(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'MOMO_SANDBOX',
            'payment_status' => 'success',
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'other', 'reason' => 'Đổi ý'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'pending_manual');
    }

    // ─── Authorization & state guards ───────────────────────────────────────

    public function test_customer_cannot_cancel_other_customers_order(): void
    {
        $other = $this->makeCustomer();
        $order = $this->makeOrder(['customer_id' => $other->id]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item'])
            ->assertStatus(403);
    }

    public function test_customer_cannot_cancel_delivering_order(): void
    {
        $order = $this->makeOrder(['status' => 'delivering']);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item'])
            ->assertStatus(422);
    }

    public function test_customer_cannot_cancel_delivered_order(): void
    {
        $order = $this->makeOrder(['status' => 'delivered']);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item'])
            ->assertStatus(422);
    }

    public function test_cancel_endpoint_requires_jwt(): void
    {
        $order = $this->makeOrder();
        $this->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'x'])
            ->assertStatus(401);
    }

    // ─── Idempotency ────────────────────────────────────────────────────────

    public function test_cancelling_twice_does_not_double_release_stock_or_double_refund(): void
    {
        $order = $this->makeOrder(['payment_method' => 'COD_SANDBOX']);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item'])
            ->assertOk();

        $this->product->refresh();
        $this->assertEquals(0, $this->product->stock_reserved);

        $movementsAfterFirst = StockMovement::where('order_id', $order->id)
            ->where('movement_type', 'unreserved')->count();
        $this->assertEquals(1, $movementsAfterFirst);

        // Second cancel — should return current state without extra stock movement / refund row change
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $movementsAfterSecond = StockMovement::where('order_id', $order->id)
            ->where('movement_type', 'unreserved')->count();
        $this->assertEquals(1, $movementsAfterSecond, 'Second cancel must not create extra unreserved movement');
    }

    // ─── Admin manual refund confirmation ───────────────────────────────────

    public function test_admin_can_confirm_manual_refund(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'BANK_SANDBOX',
            'payment_status' => 'success',
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'address_change'])
            ->assertOk();

        $adminSecret = env('ADMIN_API_SECRET');
        $this->withHeaders(['X-Admin-Secret' => $adminSecret])
            ->postJson("/api/orders/{$order->id}/refund/confirm-manual", ['note' => 'Chuyển khoản 16/05'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'refunded');

        $this->assertNotNull(ZaloOrder::find($order->id)->refunded_at);
    }

    public function test_confirm_manual_refund_rejects_non_pending_manual_orders(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'COD_SANDBOX',
            'payment_status' => 'pending',
        ]);

        // COD cancel → refund_status = not_required (not pending_manual)
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'wrong_item'])
            ->assertOk();

        $adminSecret = env('ADMIN_API_SECRET');
        $this->withHeaders(['X-Admin-Secret' => $adminSecret])
            ->postJson("/api/orders/{$order->id}/refund/confirm-manual", [])
            ->assertStatus(422);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
