<?php

namespace Tests\Unit;

use App\Models\StockMovement;
use App\Models\ZaloCategory;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockService();
    }

    private function makeProduct(array $attrs = []): ZaloProduct
    {
        static $seq = 0;
        $seq++;
        $cat = ZaloCategory::firstOrCreate(['id' => 1], ['name' => 'Test', 'image' => null]);
        return ZaloProduct::create(array_merge([
            'id'            => $seq,
            'category_id'   => $cat->id,
            'name'          => 'Product ' . $seq,
            'price'         => 10000,
            'stock'         => 100,
            'stock_reserved'=> 0,
            'reorder_point' => 10,
        ], $attrs));
    }

    private function makeOrder(int $productId, int $qty): ZaloOrder
    {
        $order = ZaloOrder::create([
            'status'         => 'pending',
            'payment_status' => 'cod',
            'total'          => 10000 * $qty,
            'note'           => '',
            'created_at'     => now(),
            'received_at'    => now()->addDays(3),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $productId,
            'name'       => 'Product',
            'price'      => 10000,
            'quantity'   => $qty,
        ]);
        return $order;
    }

    // ─── checkAvailability ────────────────────────────────────────────────────

    public function test_check_availability_returns_true_when_sufficient(): void
    {
        $p = $this->makeProduct(['stock' => 50, 'stock_reserved' => 10]);

        $result = $this->service->checkAvailability([
            ['product_id' => $p->id, 'quantity' => 30],
        ]);

        $this->assertTrue($result);
    }

    public function test_check_availability_returns_shortage_array_when_insufficient(): void
    {
        $p = $this->makeProduct(['stock' => 10, 'stock_reserved' => 5]);

        $result = $this->service->checkAvailability([
            ['product_id' => $p->id, 'quantity' => 10],
        ]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($p->id, $result[0]['product_id']);
        $this->assertEquals(5, $result[0]['available']);
    }

    // ─── reserveItems ─────────────────────────────────────────────────────────

    public function test_reserve_items_increases_stock_reserved(): void
    {
        $p     = $this->makeProduct(['stock' => 100, 'stock_reserved' => 0]);
        $order = $this->makeOrder($p->id, 5);

        $this->service->reserveItems($order->id, [
            ['product_id' => $p->id, 'quantity' => 5],
        ]);

        $p->refresh();
        $this->assertEquals(5, $p->stock_reserved);
        $this->assertEquals(100, $p->stock);
    }

    public function test_reserve_items_creates_movement_record(): void
    {
        $p     = $this->makeProduct(['stock' => 100]);
        $order = $this->makeOrder($p->id, 3);

        $this->service->reserveItems($order->id, [
            ['product_id' => $p->id, 'quantity' => 3],
        ]);

        $movement = StockMovement::where('product_id', $p->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('reserved', $movement->movement_type);
        $this->assertEquals(3, $movement->quantity_change);
        $this->assertEquals(100, $movement->quantity_before);
        $this->assertEquals(100, $movement->quantity_after);
    }

    // ─── deductOnPayment ──────────────────────────────────────────────────────

    public function test_deduct_on_payment_decreases_stock_and_reserved(): void
    {
        $p     = $this->makeProduct(['stock' => 50, 'stock_reserved' => 10]);
        $order = $this->makeOrder($p->id, 10);

        $this->service->deductOnPayment($order->id);

        $p->refresh();
        $this->assertEquals(40, $p->stock);
        $this->assertEquals(0, $p->stock_reserved);
    }

    public function test_deduct_on_payment_creates_export_movement(): void
    {
        $p     = $this->makeProduct(['stock' => 50, 'stock_reserved' => 10]);
        $order = $this->makeOrder($p->id, 10);

        $this->service->deductOnPayment($order->id);

        $movement = StockMovement::where('product_id', $p->id)
            ->where('movement_type', 'export')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-10, $movement->quantity_change);
        $this->assertEquals(50, $movement->quantity_before);
        $this->assertEquals(40, $movement->quantity_after);
    }

    // ─── releaseReservation ───────────────────────────────────────────────────

    public function test_release_reservation_decrements_stock_reserved(): void
    {
        $p     = $this->makeProduct(['stock' => 100, 'stock_reserved' => 15]);
        $order = $this->makeOrder($p->id, 15);

        $this->service->releaseReservation($order->id);

        $p->refresh();
        $this->assertEquals(0, $p->stock_reserved);
        $this->assertEquals(100, $p->stock);
    }

    public function test_release_reservation_creates_unreserved_movement(): void
    {
        $p     = $this->makeProduct(['stock' => 100, 'stock_reserved' => 5]);
        $order = $this->makeOrder($p->id, 5);

        $this->service->releaseReservation($order->id);

        $movement = StockMovement::where('product_id', $p->id)
            ->where('movement_type', 'unreserved')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(5, $movement->quantity_change);
    }

    // ─── importStock ──────────────────────────────────────────────────────────

    public function test_import_stock_increases_stock(): void
    {
        $p = $this->makeProduct(['stock' => 20]);

        $this->service->importStock($p->id, 30, 'Nhập từ nhà cung cấp', 1);

        $p->refresh();
        $this->assertEquals(50, $p->stock);
    }

    public function test_import_stock_creates_import_movement(): void
    {
        $p = $this->makeProduct(['stock' => 20]);

        $this->service->importStock($p->id, 30, 'Nhập kho test', 1);

        $movement = StockMovement::where('product_id', $p->id)
            ->where('movement_type', 'import')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(30, $movement->quantity_change);
        $this->assertEquals(20, $movement->quantity_before);
        $this->assertEquals(50, $movement->quantity_after);
        $this->assertEquals('Nhập kho test', $movement->note);
    }

    // ─── adjustStock ──────────────────────────────────────────────────────────

    public function test_adjust_stock_sets_exact_quantity(): void
    {
        $p = $this->makeProduct(['stock' => 80]);

        $this->service->adjustStock($p->id, 45, 'Kiểm kê tháng 5', 1);

        $p->refresh();
        $this->assertEquals(45, $p->stock);
    }

    public function test_adjust_stock_creates_adjustment_movement(): void
    {
        $p = $this->makeProduct(['stock' => 80]);

        $this->service->adjustStock($p->id, 45, 'Kiểm kê kho', 1);

        $movement = StockMovement::where('product_id', $p->id)
            ->where('movement_type', 'adjustment')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-35, $movement->quantity_change);
        $this->assertEquals(80, $movement->quantity_before);
        $this->assertEquals(45, $movement->quantity_after);
    }

    // ─── getLowStockProducts ──────────────────────────────────────────────────

    public function test_low_stock_scope_returns_products_at_or_below_reorder_point(): void
    {
        $low  = $this->makeProduct(['stock' => 5,   'reorder_point' => 10]);
        $exact= $this->makeProduct(['stock' => 10,  'reorder_point' => 10]);
        $ok   = $this->makeProduct(['stock' => 100, 'reorder_point' => 10]);

        $results = $this->service->getLowStockProducts();

        $ids = $results->pluck('id')->toArray();
        $this->assertContains($low->id, $ids);
        $this->assertContains($exact->id, $ids);
        $this->assertNotContains($ok->id, $ids);
    }

    // ─── stock_available accessor ─────────────────────────────────────────────

    public function test_stock_available_is_stock_minus_reserved(): void
    {
        $p = $this->makeProduct(['stock' => 100, 'stock_reserved' => 30]);
        $this->assertEquals(70, $p->stock_available);
    }

    public function test_stock_available_does_not_go_negative(): void
    {
        $p = $this->makeProduct(['stock' => 5, 'stock_reserved' => 20]);
        $this->assertEquals(0, $p->stock_available);
    }
}
