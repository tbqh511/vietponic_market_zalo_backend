<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * FarmHubTest — kiểm tra 3 nhóm chức năng cốt lõi của Farm Partner Hub:
 *
 *   A. Middleware (EnsureFarmPartner) — phân quyền đúng 401/403/200
 *   B. Dashboard /farm/products/today — sellthrough + AI hint
 *   C. FEFO allocation (StockService) — batch hết hạn sớm bị trừ trước
 *
 * Dùng SQLite :memory: (cấu hình trong phpunit.xml) — không chạy migrate thật.
 */
class FarmHubTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeCustomer(array $attrs = []): Customer
    {
        static $seq = 0;
        $seq++;
        return Customer::create(array_merge([
            'name'         => "Farm Customer {$seq}",
            'firebase_id'  => '',
            'email'        => "farm{$seq}_" . uniqid() . '@test.local',
            'mobile'       => '09' . str_pad((string)(20000000 + $seq), 8, '0', STR_PAD_LEFT),
            'address'      => '',
            'logintype'    => 'mobile',
            'isActive'     => 1,
        ], $attrs));
    }

    private function makeFarmPartner(): array
    {
        $customer = $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'approved',
        ]);

        $farm = Farm::create([
            'code'              => 'TEST' . uniqid(),
            'name'              => 'Test Farm',
            'owner_customer_id' => $customer->id,
            'address'           => 'Đà Lạt',
            'commission_rate'   => 0.1000,
            'payment_cycle'     => 'monthly',
            'is_active'         => true,
            'approved_at'       => now(),
        ]);

        return [$customer, $farm];
    }

    private function makeProduct(?int $id = null): ZaloProduct
    {
        static $seq = 0;
        $seq++;
        $pid = $id ?? (9000 + $seq);

        // Đảm bảo category tồn tại trước (FK constraint).
        \App\Models\ZaloCategory::firstOrCreate(['id' => 1], ['name' => 'Test Category']);

        return ZaloProduct::create([
            'id'          => $pid,
            'category_id' => 1,
            'name'        => "Rau Test {$seq}",
            'price'       => '15000',
        ]);
    }

    private function authHeader(Customer $customer): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($customer)];
    }

    // ─── A. Middleware tests ───────────────────────────────────────────────────

    /**
     * Không có JWT → 401 (token không tìm thấy).
     */
    public function test_farm_endpoint_requires_jwt(): void
    {
        $response = $this->getJson('/api/farm/me');

        $response->assertStatus(401);
    }

    /**
     * Customer thường (role=customer) → 403 không đủ quyền Farm Partner.
     */
    public function test_regular_customer_gets_403_on_farm_endpoint(): void
    {
        $customer = $this->makeCustomer(); // role mặc định = 'customer'

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertStatus(403)
            ->assertJson(['error' => true]);
    }

    /**
     * Customer có role=farm_partner nhưng chưa được gán Farm (hoặc farm inactive) → 403.
     */
    public function test_farm_partner_without_farm_record_gets_403(): void
    {
        // Có role đúng nhưng không có record trong bảng farms với owner_customer_id này.
        $customer = $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'approved',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertStatus(403)
            ->assertJson(['error' => true]);
    }

    /**
     * Farm partner đã duyệt + có farm active → 200 với profile farm.
     */
    public function test_approved_farm_partner_can_access_farm_me(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertOk()
            ->assertJson(['error' => false])
            ->assertJsonPath('data.name', $farm->name)
            ->assertJsonPath('data.address', $farm->address);
    }

    /**
     * Farm partner được xem dashboard overview → 200.
     */
    public function test_farm_partner_can_access_dashboard_overview(): void
    {
        [$customer] = $this->makeFarmPartner();

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/dashboard?range=today');

        $response->assertOk()
            ->assertJson(['error' => false])
            ->assertJsonStructure(['data' => ['range', 'revenue', 'orders_count', 'items_sold']]);
    }

    // ─── B. /farm/products/today — sellthrough + AI hint ─────────────────────

    /**
     * Khi không có batch nào hôm nay → products=[] và hint=null.
     */
    public function test_products_today_empty_when_no_batches(): void
    {
        [$customer] = $this->makeFarmPartner();

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/products/today');

        $response->assertOk();
        // Khi không có sản phẩm, controller trả data=[] (early return).
        $data = $response->json('data');
        $products = $data['products'] ?? $data;
        $this->assertEmpty($products);
    }

    /**
     * Farm nhập hàng hôm nay + có đơn bán đang giao → tính đúng sellthrough_pct.
     */
    public function test_products_today_computes_sellthrough_correctly(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Batch hôm nay: nhập 20kg
        FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now('Asia/Ho_Chi_Minh')->toDateString(),
            'quantity_in'        => 20,
            'quantity_sold'      => 0,
            'quantity_remaining' => 20, // SQLite fallback (MySQL tự tính)
            'cost_price'         => 10000,
            'status'             => 'active',
        ]);

        // Tạo order 'delivering' hôm nay — dùng UTC để khớp với controller BETWEEN.
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivering',
            'payment_status' => 'pending',
            'total'          => '120000',
            'created_at'     => \Carbon\Carbon::now('UTC'),
        ]);

        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 8,
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/products/today');

        $response->assertOk();
        $products = $response->json('data.products');

        $this->assertCount(1, $products);
        $row = $products[0];
        $this->assertEquals($product->id, $row['product_id']);
        $this->assertEquals(20.0, $row['stocked']);
        $this->assertEquals(8.0, $row['sold']);
        // sellthrough = 8/20 * 100 = 40%
        $this->assertEquals(40.0, $row['sellthrough_pct']);
        $this->assertEquals('good', $row['status']); // 40% < 70%
    }

    /**
     * Khi sellthrough >= 95% → status='danger' + AI hint type='restock'.
     */
    public function test_high_sellthrough_triggers_restock_hint(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Nhập 10kg, đã bán 10kg (sellthrough = 100%)
        $batch = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now('Asia/Ho_Chi_Minh')->toDateString(),
            'quantity_in'        => 10,
            'quantity_sold'      => 10,
            'quantity_remaining' => 0, // SQLite fallback (MySQL tự tính: 10-10=0)
            'cost_price'         => 10000,
            'status'             => 'depleted', // đã hết
        ]);

        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '150000',
            'created_at'     => \Carbon\Carbon::now('UTC'),
        ]);

        ZaloOrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'farm_stock_batch_id' => $batch->id,
            'name'                => $product->name,
            'price'               => '15000',
            'quantity'            => 10,
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/products/today');

        $response->assertOk();
        $products = $response->json('data.products');
        $this->assertNotEmpty($products);

        $row = $products[0];
        $this->assertEquals(100.0, $row['sellthrough_pct']);
        $this->assertEquals('danger', $row['status']);

        $hint = $response->json('data.hint');
        $this->assertNotNull($hint);
        $this->assertEquals('restock', $hint['type']);
        $this->assertStringContainsString($product->name, $hint['message']);
    }

    /**
     * Khi stocked=0 nhưng có bán (bán từ batch cũ) → sellthrough_pct=999 (sentinel).
     */
    public function test_sellthrough_sentinel_999_when_no_batch_today_but_sold(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // KHÔNG có batch hôm nay. Nhưng có batch cũ (hôm qua) còn active.
        FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now('Asia/Ho_Chi_Minh')->subDay()->toDateString(),
            'quantity_in'        => 20,
            'quantity_sold'      => 5,
            'quantity_remaining' => 15, // SQLite fallback (MySQL tự tính: 20-5=15)
            'cost_price'         => 10000,
            'status'             => 'active',
        ]);

        // Đơn tạo hôm nay (UTC) — controller BETWEEN dùng UTC bounds.
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivering',
            'payment_status' => 'pending',
            'total'          => '75000',
            'created_at'     => \Carbon\Carbon::now('UTC'),
        ]);

        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 5,
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/products/today');

        $response->assertOk();
        $products = $response->json('data.products');
        $this->assertNotEmpty($products);
        // stocked = 0 (không có batch hôm nay), sold = 5 → sentinel 999
        $this->assertEquals(999.0, $products[0]['sellthrough_pct']);
    }

    // ─── C. FEFO allocation (StockService) ────────────────────────────────────

    /**
     * Khi có 2 batch: batch sắp hết hạn phải bị trừ trước (FEFO).
     *
     * Scenario:
     *   - Batch A: expire 3 ngày tới, còn 5kg
     *   - Batch B: expire 10 ngày tới, còn 10kg
     *   - Order cần 7kg → phải lấy 5kg từ A, 2kg từ B
     */
    public function test_fefo_allocates_earliest_expiry_first(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Batch A: hết hạn sớm hơn (3 ngày tới)
        $batchA = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 5,
            'quantity_sold'      => 0,
            'quantity_remaining' => 5, // SQLite fallback
            'cost_price'         => 10000,
            'expire_date'        => now()->addDays(3)->toDateString(),
            'status'             => 'active',
        ]);

        // Batch B: hết hạn muộn hơn (10 ngày tới)
        $batchB = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 10,
            'quantity_sold'      => 0,
            'quantity_remaining' => 10, // SQLite fallback
            'cost_price'         => 10000,
            'expire_date'        => now()->addDays(10)->toDateString(),
            'status'             => 'active',
        ]);

        // Tạo order + order_item cần 7kg (chưa gắn batch)
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
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

        // Chạy FEFO allocation
        $service = app(StockService::class);
        $service->reserveItems($order->id, [
            ['product_id' => $product->id, 'quantity' => 7],
        ]);

        // Kiểm tra batch A bị trừ hết (5kg) → status depleted
        $batchA->refresh();
        $this->assertEquals('5.00', $batchA->quantity_sold);
        $this->assertEquals('depleted', $batchA->status);

        // Kiểm tra batch B bị trừ 2kg (7 - 5)
        $batchB->refresh();
        $this->assertEquals('2.00', $batchB->quantity_sold);
        $this->assertEquals('active', $batchB->status);

        // Kiểm tra order_items được split thành 2 row (1 per batch)
        $items = ZaloOrderItem::where('order_id', $order->id)
            ->where('product_id', $product->id)
            ->get();

        $this->assertCount(2, $items);

        $batchIds = $items->pluck('farm_stock_batch_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $this->assertContains((int) $batchA->id, $batchIds);
        $this->assertContains((int) $batchB->id, $batchIds);

        $qtyForA = $items->where('farm_stock_batch_id', $batchA->id)->first()->quantity;
        $qtyForB = $items->where('farm_stock_batch_id', $batchB->id)->first()->quantity;
        $this->assertEquals('5.00', $qtyForA);
        $this->assertEquals('2.00', $qtyForB);
    }

    /**
     * Batch không có expire_date bị xếp sau batch có expire_date (FEFO).
     */
    public function test_fefo_batch_with_no_expiry_allocated_last(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Batch A: có expire_date → ưu tiên dùng trước
        $batchA = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 3,
            'quantity_sold'      => 0,
            'quantity_remaining' => 3, // SQLite fallback
            'cost_price'         => 10000,
            'expire_date'        => now()->addDays(5)->toDateString(),
            'status'             => 'active',
        ]);

        // Batch B: KHÔNG có expire_date → FEFO để sau cùng
        $batchB = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 10,
            'quantity_sold'      => 0,
            'quantity_remaining' => 10, // SQLite fallback
            'cost_price'         => 10000,
            'expire_date'        => null,
            'status'             => 'active',
        ]);

        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'total'          => '60000',
            'created_at'     => now(),
        ]);

        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 4,
        ]);

        app(StockService::class)->reserveItems($order->id, [
            ['product_id' => $product->id, 'quantity' => 4],
        ]);

        // Batch A (có hạn) phải dùng trước (cả 3kg)
        $batchA->refresh();
        $this->assertEquals('3.00', $batchA->quantity_sold);
        $this->assertEquals('depleted', $batchA->status);

        // Batch B (không hạn) chỉ bù 1kg còn thiếu
        $batchB->refresh();
        $this->assertEquals('1.00', $batchB->quantity_sold);
        $this->assertEquals('active', $batchB->status);
    }

    /**
     * checkAvailability() trả shortage khi tổng tồn không đủ.
     */
    public function test_check_availability_returns_shortage_when_insufficient(): void
    {
        [, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Chỉ có 3kg
        FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 3,
            'quantity_sold'      => 0,
            'quantity_remaining' => 3, // SQLite fallback
            'cost_price'         => 10000,
            'status'             => 'active',
        ]);

        $service = app(StockService::class);
        $result = $service->checkAvailability([
            ['product_id' => $product->id, 'quantity' => 10],
        ]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($product->id, $result[0]['product_id']);
        $this->assertEquals(10.0, $result[0]['requested']);
        $this->assertEquals(3.0, $result[0]['available']);
    }

    /**
     * checkAvailability() trả true khi đủ tồn.
     */
    public function test_check_availability_returns_true_when_sufficient(): void
    {
        [, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 20,
            'quantity_sold'      => 0,
            'quantity_remaining' => 20, // SQLite fallback
            'cost_price'         => 10000,
            'status'             => 'active',
        ]);

        $result = app(StockService::class)->checkAvailability([
            ['product_id' => $product->id, 'quantity' => 15],
        ]);

        $this->assertTrue($result);
    }

    // ─── D. FarmDashboardService — revenue calculation ────────────────────────

    /**
     * getOverview() chỉ tính đơn 'delivered' (không tính 'delivering').
     */
    public function test_overview_counts_only_delivered_orders(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Đơn 1: delivered → tính revenue. Dùng UTC để khớp với itemsBaseQuery.
        $delivered = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '200000',
            'delivered_at'   => \Carbon\Carbon::now('UTC')->subHours(2),
            'created_at'     => \Carbon\Carbon::now('UTC')->subHours(3),
        ]);

        ZaloOrderItem::create([
            'order_id'            => $delivered->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 10,
            'cost_price_snapshot' => '10000',
        ]);

        // Đơn 2: delivering → KHÔNG tính (chưa xác nhận xong)
        $delivering = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivering',
            'payment_status' => 'pending',
            'total'          => '150000',
            'created_at'     => \Carbon\Carbon::now('UTC')->subHour(),
        ]);

        ZaloOrderItem::create([
            'order_id'   => $delivering->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 10,
        ]);

        $service = app(\App\Services\FarmDashboardService::class);
        $overview = $service->getOverview($farm, 'today');

        // Chỉ đơn delivered được tính
        $this->assertEquals(1, $overview['orders_count']);
        $this->assertEquals(200000.0, (float) $overview['revenue']);
        // profit = revenue - cost = 200000 - 100000
        $this->assertEquals(100000.0, (float) $overview['profit']);
    }
}
