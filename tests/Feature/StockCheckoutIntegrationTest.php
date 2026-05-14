<?php

namespace Tests\Feature;

use App\Events\OrderPaymentSucceeded;
use App\Models\Customer;
use App\Models\StockMovement;
use App\Models\ZaloCategory;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StockCheckoutIntegrationTest extends TestCase
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

    private function authHeaders(): array
    {
        $token = JWTAuth::fromUser($this->customer);
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function orderPayload(int $qty = 2): array
    {
        return [
            'items'      => [[
                'product_id' => (string) $this->product->id,
                'name'       => $this->product->name,
                'price'      => (string) $this->product->price,
                'quantity'   => (string) $qty,
                'image'      => '',
                'detail'     => '',
            ]],
            'delivery'   => [
                'type'       => 'shipping',
                'address'    => '123 Lý Tự Trọng, Đà Lạt',
                'name'       => 'Nguyễn Văn A',
                'phone'      => '0912345678',
                'station_id' => null,
            ],
            'total'      => (string) ($this->product->price * $qty),
            'note'       => '',
            'created_at' => now()->toIso8601String(),
        ];
    }

    // ─── Order creation with stock check ─────────────────────────────────────

    public function test_order_creation_succeeds_when_stock_sufficient(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload(2));

        $response->assertCreated();
        $response->assertJsonFragment(['message' => 'Đã tạo đơn hàng thành công!']);
    }

    public function test_order_creation_fails_when_product_out_of_stock(): void
    {
        $this->product->update(['stock' => 1, 'stock_reserved' => 0]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload(5));

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => true]);
        $response->assertJsonPath('shortages.0.product_id', $this->product->id);
    }

    public function test_order_creation_reserves_stock(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload(3));

        $response->assertCreated();
        $this->product->refresh();
        $this->assertEquals(3, $this->product->stock_reserved);
        $this->assertEquals(50, $this->product->stock);
    }

    public function test_reserved_stock_reduces_available_quantity(): void
    {
        // Reserve 48 — only 2 remain
        $this->product->update(['stock_reserved' => 48]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload(3));

        $response->assertStatus(422);
    }

    // ─── Payment deduction via event ─────────────────────────────────────────

    public function test_payment_success_deducts_stock_via_event(): void
    {
        $order = ZaloOrder::create([
            'customer_id'    => $this->customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'total'          => 40000,
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'name'       => $this->product->name,
            'price'      => $this->product->price,
            'quantity'   => 2,
        ]);
        $this->product->update(['stock_reserved' => 2]);

        event(new OrderPaymentSucceeded($order->id));

        $this->product->refresh();
        $this->assertEquals(48, $this->product->stock);
        $this->assertEquals(0, $this->product->stock_reserved);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'    => $this->product->id,
            'order_id'      => $order->id,
            'movement_type' => 'export',
            'quantity_change'=> -2,
        ]);
    }

    // ─── Cancellation releases reservation ───────────────────────────────────

    public function test_order_cancellation_releases_reserved_stock(): void
    {
        $order = ZaloOrder::create([
            'status'         => 'confirmed',
            'payment_status' => 'pending',
            'total'          => 40000,
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'name'       => $this->product->name,
            'price'      => $this->product->price,
            'quantity'   => 4,
        ]);
        $this->product->update(['stock_reserved' => 4]);

        $adminSecret = env('ADMIN_API_SECRET');
        $this->withHeaders(['X-Admin-Secret' => $adminSecret])
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->product->refresh();
        $this->assertEquals(0, $this->product->stock_reserved);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'    => $this->product->id,
            'movement_type' => 'unreserved',
        ]);
    }

    public function test_cancelling_already_cancelled_order_does_not_double_release(): void
    {
        $order = ZaloOrder::create([
            'status'         => 'cancelled',
            'payment_status' => 'cod',
            'total'          => 0,
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'name'       => 'test',
            'price'      => 0,
            'quantity'   => 1,
        ]);
        $this->product->update(['stock_reserved' => 5]);

        $adminSecret = env('ADMIN_API_SECRET');
        $this->withHeaders(['X-Admin-Secret' => $adminSecret])
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        // stock_reserved should stay at 5 (no additional release)
        $this->product->refresh();
        $this->assertEquals(5, $this->product->stock_reserved);
    }
}
