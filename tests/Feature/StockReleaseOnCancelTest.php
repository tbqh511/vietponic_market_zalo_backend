<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloCategory;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BuildsReservedOrderFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * StockReleaseOnCancelTest (B6 — STOCK-06 + ORDPRO-08 phần kho).
 *
 * Khoá hành vi hoàn kho khi đơn bị HUỶ trên các đường:
 *   - Khách huỷ (POST /orders/{id}/cancel)
 *   - Admin huỷ (PATCH /orders/{id}/status cancelled)
 *
 * Khẳng định: quantity_sold giảm đúng theo từng item, quantity_remaining hoàn
 * đủ, lô depleted→active, và (ORDPRO-08) refund_status='not_required' cho đơn COD.
 *
 * Nhánh job auto-cancel (B1) được phủ riêng ở CancelUnpaidOrderTest; nhánh
 * webhook VTP ở ViettelPostWebhookTest.
 */
class StockReleaseOnCancelTest extends TestCase
{
    use RefreshDatabase, BuildsReservedOrderFactory;

    private function customerAuthHeaders(Customer $customer): array
    {
        $token = JWTAuth::claims(['customer_id' => $customer->id])->fromUser($customer);
        return ['Authorization' => "Bearer {$token}"];
    }

    private function adminAuthHeaders(): array
    {
        return ['X-Admin-Secret' => env('ADMIN_API_SECRET')];
    }

    // ── Khách huỷ ──────────────────────────────────────────────────────────────

    public function test_customer_cancel_cod_restores_stock_per_item(): void
    {
        // Đơn COD pending đã reserve 4/10 → quantity_sold=4, remaining=6.
        [$order, $batch] = $this->makeReservedOrder([
            'payment_method' => 'COD',
            'payment_status' => 'cod',
        ], orderQty: 4, batchQty: 10);

        $this->assertEquals('4.00', $batch->fresh()->quantity_sold);
        $this->assertEquals('6.00', $batch->fresh()->quantity_remaining);

        $buyer = Customer::findOrFail($order->customer_id);
        $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'customer_other'])
            ->assertStatus(200);

        $this->assertSame('cancelled', $order->fresh()->status);

        // Kho hoàn đúng phần đã trừ: sold về 0, remaining về đủ 10.
        $fresh = $batch->fresh();
        $this->assertEquals('0.00', $fresh->quantity_sold);
        $this->assertEquals('10.00', $fresh->quantity_remaining);
        $this->assertSame('active', $fresh->status);
    }

    public function test_cancel_reverts_depleted_batch_to_active(): void
    {
        // Mua sạch lô (10/10) → batch depleted, remaining=0.
        [$order, $batch] = $this->makeReservedOrder([
            'payment_method' => 'COD',
            'payment_status' => 'cod',
        ], orderQty: 10, batchQty: 10);

        $depleted = $batch->fresh();
        $this->assertEquals('10.00', $depleted->quantity_sold);
        $this->assertEquals('0.00', $depleted->quantity_remaining);
        $this->assertSame('depleted', $depleted->status);

        $buyer = Customer::findOrFail($order->customer_id);
        $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'customer_other'])
            ->assertStatus(200);

        // Huỷ → trả hàng về → lô bán tiếp được.
        $restored = $batch->fresh();
        $this->assertEquals('0.00', $restored->quantity_sold);
        $this->assertEquals('10.00', $restored->quantity_remaining);
        $this->assertSame('active', $restored->status);
    }

    public function test_cancel_multi_batch_order_restores_each_batch(): void
    {
        ZaloCategory::firstOrCreate(['id' => 1], ['name' => 'Test Category']);

        $buyer = $this->makeReservedCustomer('multibatch-buyer');
        $owner = $this->makeReservedCustomer('multibatch-owner');

        $farm = Farm::create([
            'code'              => 'MB' . uniqid(),
            'name'              => 'Multi Batch Farm',
            'owner_customer_id' => $owner->id,
            'address'           => 'Đà Lạt',
            'commission_rate'   => 0.1000,
            'payment_cycle'     => 'monthly',
            'is_active'         => true,
            'approved_at'       => now(),
        ]);

        $product = ZaloProduct::create([
            'id'          => 9500,
            'category_id' => 1,
            'name'        => 'Rau Multi Batch',
            'price'       => '15000',
        ]);

        // Lô A hết hạn sớm (5kg) — FEFO trừ trước; lô B muộn hơn (10kg).
        $batchA = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 5,
            'quantity_sold'      => 0,
            'quantity_remaining' => 5,
            'cost_price'         => 10000,
            'expire_date'        => now()->addDays(3)->toDateString(),
            'status'             => 'active',
        ]);
        $batchB = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 10,
            'quantity_sold'      => 0,
            'quantity_remaining' => 10,
            'cost_price'         => 10000,
            'expire_date'        => now()->addDays(10)->toDateString(),
            'status'             => 'active',
        ]);

        $order = ZaloOrder::create([
            'customer_id'    => $buyer->id,
            'status'         => 'pending',
            'payment_method' => 'COD',
            'payment_status' => 'cod',
            'total'          => '105000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 7,
        ]);

        // Reserve 7kg → A=5 (depleted), B=2 (active).
        app(StockService::class)->reserveItems($order->id, [
            ['product_id' => $product->id, 'quantity' => 7],
        ]);
        $this->assertEquals('5.00', $batchA->fresh()->quantity_sold);
        $this->assertSame('depleted', $batchA->fresh()->status);
        $this->assertEquals('2.00', $batchB->fresh()->quantity_sold);
        $this->assertSame('active', $batchB->fresh()->status);

        $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'customer_other'])
            ->assertStatus(200);

        // Mỗi lô hoàn đúng phần của mình.
        $freshA = $batchA->fresh();
        $this->assertEquals('0.00', $freshA->quantity_sold);
        $this->assertEquals('5.00', $freshA->quantity_remaining);
        $this->assertSame('active', $freshA->status); // depleted → active

        $freshB = $batchB->fresh();
        $this->assertEquals('0.00', $freshB->quantity_sold);
        $this->assertEquals('10.00', $freshB->quantity_remaining);
        $this->assertSame('active', $freshB->status);
    }

    // ── Admin huỷ ────────────────────────────────────────────────────────────

    public function test_admin_cancel_restores_stock(): void
    {
        [$order, $batch] = $this->makeReservedOrder([
            'status'         => 'preparing',
            'payment_method' => 'COD',
            'payment_status' => 'cod',
        ], orderQty: 4, batchQty: 10);

        $this->assertEquals('4.00', $batch->fresh()->quantity_sold);

        $this->withHeaders($this->adminAuthHeaders())
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);

        $fresh = $batch->fresh();
        $this->assertEquals('0.00', $fresh->quantity_sold);
        $this->assertEquals('10.00', $fresh->quantity_remaining);
        $this->assertSame('active', $fresh->status);
    }

    // ── ORDPRO-08: khách huỷ COD → refund_status not_required + hoàn kho ──────

    public function test_customer_cancel_cod_sets_refund_status_not_required_and_restores_stock(): void
    {
        // Đơn COD chưa thanh toán thành công.
        [$order, $batch] = $this->makeReservedOrder([
            'payment_method' => 'COD',
            'payment_status' => 'cod',
        ], orderQty: 4, batchQty: 10);

        $buyer = Customer::findOrFail($order->customer_id);
        $this->withHeaders($this->customerAuthHeaders($buyer))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'customer_other'])
            ->assertStatus(200);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        // (1) Không phát sinh hoàn tiền cho đơn COD chưa trả.
        $this->assertSame('not_required', $fresh->refund_status);
        // (2) Kho được hoàn.
        $this->assertEquals('0.00', $batch->fresh()->quantity_sold);
        $this->assertEquals('10.00', $batch->fresh()->quantity_remaining);
    }
}
