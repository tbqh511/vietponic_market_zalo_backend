<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmPayout;
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

        // EnsureFarmPartner tra farm theo customers.farm_id (owner + staff),
        // không còn theo farms.owner_customer_id (xem migration 2026_05_19_100000).
        $customer->forceFill(['farm_id' => $farm->id, 'farm_role' => 'owner'])->save();

        return [$customer, $farm];
    }

    /**
     * Nhân viên (staff) của một farm: cũng là farm_partner đã duyệt, có farm_id
     * trỏ tới farm đang active → PASS EnsureFarmPartner. Khác owner ở farm_role,
     * nên bị chặn ở gate chỉ-owner (payout).
     */
    private function makeFarmStaff(Farm $farm): Customer
    {
        $customer = $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'approved',
        ]);
        $customer->forceFill(['farm_id' => $farm->id, 'farm_role' => 'staff'])->save();

        return $customer;
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

    protected function tearDown(): void
    {
        // Một số test "hôm nay" freeze giờ VN bằng setTestNow — reset để không
        // rò sang test khác.
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
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
     * Discriminator code=FARM_PARTNER_REQUIRED + message GIỮ NGUYÊN nguyên văn
     * (hợp đồng ROLE-02: FE/test khác dựa vào đúng chuỗi này).
     */
    public function test_regular_customer_gets_403_on_farm_endpoint(): void
    {
        $customer = $this->makeCustomer(); // role mặc định = 'customer'

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertStatus(403)
            ->assertJson(['error' => true])
            ->assertJsonPath('code', 'FARM_PARTNER_REQUIRED')
            ->assertJsonPath('message', 'Bạn không có quyền truy cập chức năng Farm Partner');
    }

    /**
     * Customer có role=farm_partner nhưng chưa được gán Farm (không có row) → 403
     * code=FARM_NOT_ASSIGNED. Phân biệt với farm bị suspend (FARM_SUSPENDED).
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
            ->assertJson(['error' => true])
            ->assertJsonPath('code', 'FARM_NOT_ASSIGNED');
    }

    /**
     * ROLE-05 — partner bị admin suspend (farm_partner_status='suspended') → 403
     * với message "tạm dừng" THỐNG NHẤT (chung với farm is_active=false) + code
     * FARM_SUSPENDED. isFarmPartner() trả false cho cả 'suspended' lẫn 'requested',
     * nên middleware phải branch theo status để chọn đúng message.
     */
    public function test_suspended_farm_partner_gets_403_paused(): void
    {
        $customer = $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'suspended',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => true,
                'code'    => 'FARM_SUSPENDED',
                'message' => 'Farm của bạn đang tạm dừng, vui lòng liên hệ admin',
            ]);
    }

    /**
     * ROLE-05 — farm record TỒN TẠI nhưng is_active=false (từng active rồi tắt) →
     * cùng message "tạm dừng" + code FARM_SUSPENDED. Chứng minh middleware lookup
     * KHÔNG dùng scopeActive (vốn gộp inactive với no-record thành null) mà branch
     * theo is_active để phân biệt với "chưa được gán".
     */
    public function test_farm_partner_with_inactive_farm_gets_403_paused(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $farm->forceFill(['is_active' => false])->save();

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => true,
                'code'    => 'FARM_SUSPENDED',
                'message' => 'Farm của bạn đang tạm dừng, vui lòng liên hệ admin',
            ]);
    }

    /**
     * ROLE-02 — partner đã đăng ký, CHỜ DUYỆT (farm_partner_status='requested') →
     * 403 PHÂN BIỆT ĐƯỢC với suspended qua code FARM_PARTNER_REQUIRED, message giữ
     * nguyên văn. FE đọc code/farm_partner_status để hiện màn "Đang chờ duyệt" thay
     * vì đẩy lại form đăng ký.
     */
    public function test_requested_farm_partner_gets_403_distinguishable(): void
    {
        $customer = $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'requested',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertStatus(403)
            ->assertJson(['error' => true])
            ->assertJsonPath('code', 'FARM_PARTNER_REQUIRED')
            ->assertJsonPath('message', 'Bạn không có quyền truy cập chức năng Farm Partner');
    }

    /**
     * Farm partner hợp lệ nhưng bị admin vô hiệu hoá (isActive=0) → 403 + code
     * ACCOUNT_DISABLED. Trước đây EnsureFarmPartner trả nhầm 401 không kèm code,
     * khiến frontend tưởng token hết hạn và re-auth lặp thay vì báo "vô hiệu hoá".
     */
    public function test_disabled_farm_partner_gets_403_account_disabled(): void
    {
        [$customer] = $this->makeFarmPartner();
        $customer->forceFill(['isActive' => 0])->save();

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertStatus(403)
            ->assertJson([
                'error' => true,
                'code'  => 'ACCOUNT_DISABLED',
            ]);
    }

    /**
     * Customer thường bị vô hiệu hoá gọi route cần JWT (zalo.jwt) → 403 + code
     * ACCOUNT_DISABLED (ZaloJwtMiddleware). Cùng hợp đồng với chốt farm ở trên.
     */
    public function test_disabled_customer_gets_403_account_disabled_on_jwt_route(): void
    {
        $customer = $this->makeCustomer(['isActive' => 0]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/orders');

        $response->assertStatus(403)
            ->assertJson([
                'error' => true,
                'code'  => 'ACCOUNT_DISABLED',
            ]);
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
        // Khi không có sản phẩm, cả 2 nhóm rỗng.
        $this->assertEmpty($response->json('data.products_placed'));
        $this->assertEmpty($response->json('data.products_delivered'));
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

        // Tạo order 'delivering' hôm nay — now() lưu giờ VN giống production.
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivering',
            'payment_status' => 'pending',
            'total'          => '120000',
            'created_at'     => now(),
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
        // Đơn delivering created hôm nay → nhóm placed.
        $products = $response->json('data.products_placed');

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
            'created_at'     => now(),
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
        // Đơn created hôm nay → nhóm placed; hint AI tính trên placed.
        $products = $response->json('data.products_placed');
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

        // Đơn tạo hôm nay — now() lưu giờ VN, khớp cửa sổ "hôm nay" giờ VN.
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivering',
            'payment_status' => 'pending',
            'total'          => '75000',
            'created_at'     => now(),
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
        // Đơn delivering created hôm nay → nhóm placed.
        $products = $response->json('data.products_placed');
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

        // Đơn 1: delivered → tính revenue. now() lưu giờ VN, khớp itemsBaseQuery.
        $delivered = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '200000',
            'delivered_at'   => now()->subHours(2),
            'created_at'     => now()->subHours(3),
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
            'created_at'     => now()->subHour(),
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

        // Khối 'delivered' (HUB-01) phản chiếu đúng số top-level (cùng basis "đã giao").
        $this->assertEquals(1, $overview['delivered']['orders_count']);
        $this->assertEquals(200000.0, (float) $overview['delivered']['revenue']);
    }

    // ─── HUB-01: tách 2 chỉ số "đã đặt" (placed) / "đã giao" (delivered) ────────

    /**
     * Khối overview['placed'] gồm MỌI đơn created_at hôm nay TRỪ 'cancelled'
     * (pending + delivering vào, cancelled không vào). Basis = created_at.
     */
    public function test_overview_placed_includes_today_orders_all_statuses_except_cancelled(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Đơn pending hôm nay → VÀO placed.
        $pending = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'total'          => '100000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'            => $pending->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 3,
            'cost_price_snapshot' => '10000',
        ]);

        // Đơn delivering hôm nay → VÀO placed.
        $delivering = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivering',
            'payment_status' => 'pending',
            'total'          => '40000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'            => $delivering->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 2,
            'cost_price_snapshot' => '10000',
        ]);

        // Đơn cancelled hôm nay → KHÔNG vào placed.
        $cancelled = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'cancelled',
            'payment_status' => 'pending',
            'total'          => '999000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'            => $cancelled->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 50,
            'cost_price_snapshot' => '10000',
        ]);

        $overview = app(\App\Services\FarmDashboardService::class)->getOverview($farm, 'today');

        // 2 đơn (pending + delivering), cancelled bị loại.
        $this->assertEquals(2, $overview['placed']['orders_count']);
        // revenue placed = 3*20000 + 2*20000 = 100000 (cancelled 50*20000 không tính).
        $this->assertEquals(100000.0, (float) $overview['placed']['revenue']);
        $this->assertEquals(5.0, (float) $overview['placed']['items_sold']);
        // delivered = 0 (chưa đơn nào giao).
        $this->assertEquals(0, $overview['delivered']['orders_count']);
    }

    /**
     * Đơn đặt HÔM QUA, giao HÔM NAY → chỉ vào 'delivered', KHÔNG vào 'placed'.
     * Đảm bảo 2 basis độc lập (placed=created_at, delivered=delivered_at).
     */
    public function test_overview_delivered_does_not_leak_into_placed(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '60000',
            // Đặt hôm qua, giao hôm nay (đầu giờ chiều VN để chắc chắn trong ngày).
            'created_at'     => \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->subDay()->startOfDay()->addHours(10),
            // Giao hôm nay, đầu ngày VN (01:00) → luôn ≤ now() bất kể giờ chạy test.
            'delivered_at'   => \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->startOfDay()->addHour(),
        ]);
        ZaloOrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 3,
            'cost_price_snapshot' => '10000',
        ]);

        $overview = app(\App\Services\FarmDashboardService::class)->getOverview($farm, 'today');

        $this->assertEquals(1, $overview['delivered']['orders_count']);
        $this->assertEquals(60000.0, (float) $overview['delivered']['revenue']);
        // created_at hôm qua → KHÔNG vào placed.
        $this->assertEquals(0, $overview['placed']['orders_count']);
        $this->assertEquals(0.0, (float) $overview['placed']['revenue']);
    }

    /**
     * Scope farm: item của farm khác trong cùng đơn KHÔNG lẫn vào placed của farm này.
     */
    public function test_overview_placed_scoped_to_current_farm(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        [, $otherFarm]     = $this->makeFarmPartner();
        $product = $this->makeProduct();

        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'total'          => '100000',
            'created_at'     => now(),
        ]);
        // Item của farm hiện tại.
        ZaloOrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 2,
            'cost_price_snapshot' => '10000',
        ]);
        // Item của farm KHÁC cùng đơn → không được tính vào placed của $farm.
        ZaloOrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'farm_id'             => $otherFarm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 9,
            'cost_price_snapshot' => '10000',
        ]);

        $overview = app(\App\Services\FarmDashboardService::class)->getOverview($farm, 'today');

        $this->assertEquals(1, $overview['placed']['orders_count']);
        // Chỉ 2kg của farm hiện tại, không gồm 9kg farm khác.
        $this->assertEquals(2.0, (float) $overview['placed']['items_sold']);
        $this->assertEquals(40000.0, (float) $overview['placed']['revenue']);
    }

    // ─── HUB-01 TZ-fix (B18): "hôm nay" phải theo giờ VN, không lệch 7h ─────────
    // Production lưu created_at/delivered_at theo GIỜ VN (app.timezone=Asia/Ho_Chi_Minh,
    // delivered_at=now(), created_at từ FE gửi +07:00). Bug cũ convert range sang UTC
    // (tưởng mốc lưu UTC) → cửa sổ "hôm nay" lệch -7h. 2 test dưới freeze giờ VN để
    // tái hiện tất định: ĐỎ trên code cũ, XANH sau fix.

    /**
     * Đơn GIAO lúc tối muộn (21:30) giờ VN HÔM NAY phải vào 'delivered'.
     * Bug cũ: range upper bị dịch về 14:30 UTC → đơn 21:30 VN bị rớt khỏi hôm nay.
     */
    public function test_overview_today_counts_order_delivered_late_evening_vn(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-12 21:30:00', 'Asia/Ho_Chi_Minh'));

        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Giao "bây giờ" = 21:30 hôm nay giờ VN; lưu giờ VN giống production now().
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '80000',
            'created_at'     => now(),     // giờ VN
            'delivered_at'   => now(),     // 21:30 VN hôm nay
        ]);
        ZaloOrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 4,
            'cost_price_snapshot' => '10000',
        ]);

        $overview = app(\App\Services\FarmDashboardService::class)->getOverview($farm, 'today');

        // Phải vào 'delivered' (giao hôm nay) và cả 'placed' (đặt hôm nay).
        $this->assertEquals(1, $overview['delivered']['orders_count']);
        $this->assertEquals(80000.0, (float) $overview['delivered']['revenue']);
        $this->assertEquals(1, $overview['placed']['orders_count']);
    }

    /**
     * Đơn ĐẶT 22:00 HÔM QUA giờ VN KHÔNG được lọt vào "đã đặt hôm nay".
     * Bug cũ: range lệch -7h kéo đơn 17:00–24:00 VN hôm qua vào hôm nay.
     */
    public function test_overview_today_excludes_order_placed_last_evening_vn(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-12 21:30:00', 'Asia/Ho_Chi_Minh'));

        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        // Đặt 22:00 hôm qua giờ VN (lưu giờ VN), chưa giao.
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'total'          => '60000',
            'created_at'     => \Carbon\Carbon::parse('2026-06-11 22:00:00', 'Asia/Ho_Chi_Minh'),
        ]);
        ZaloOrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 3,
            'cost_price_snapshot' => '10000',
        ]);

        $overview = app(\App\Services\FarmDashboardService::class)->getOverview($farm, 'today');

        // created_at hôm qua → KHÔNG vào placed/delivered hôm nay.
        $this->assertEquals(0, $overview['placed']['orders_count']);
        $this->assertEquals(0, $overview['delivered']['orders_count']);
    }

    /**
     * /farm/products/today trả 2 nhóm tách basis:
     *   - products_placed: đơn created_at hôm nay (trừ cancelled)
     *   - products_delivered: đơn delivered + delivered_at hôm nay
     * Đơn đặt hôm nay CHƯA giao → chỉ vào placed.
     */
    public function test_products_today_splits_placed_and_delivered(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now('Asia/Ho_Chi_Minh')->toDateString(),
            'quantity_in'        => 20,
            'quantity_sold'      => 0,
            'quantity_remaining' => 20,
            'cost_price'         => 10000,
            'status'             => 'active',
        ]);

        // Đơn đặt hôm nay, đang giao (chưa delivered) → vào placed, KHÔNG vào delivered.
        $placedOrder = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivering',
            'payment_status' => 'pending',
            'total'          => '120000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $placedOrder->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 8,
        ]);

        // Đơn delivered hôm nay (created hôm qua) → vào delivered, KHÔNG vào placed.
        $deliveredOrder = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '45000',
            'created_at'     => \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->subDay()->startOfDay()->addHours(10),
            'delivered_at'   => \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->startOfDay()->addHour(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $deliveredOrder->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 3,
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/products/today');

        $response->assertOk();

        $placed = $response->json('data.products_placed');
        $this->assertCount(1, $placed);
        $this->assertEquals(8.0, $placed[0]['sold']);

        $delivered = $response->json('data.products_delivered');
        $this->assertCount(1, $delivered);
        $this->assertEquals(3.0, $delivered[0]['sold']);
    }

    /**
     * Đơn cancelled hôm nay KHÔNG cộng vào products_placed.sold.
     */
    public function test_products_today_cancelled_excluded_from_placed(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now('Asia/Ho_Chi_Minh')->toDateString(),
            'quantity_in'        => 20,
            'quantity_sold'      => 0,
            'quantity_remaining' => 20,
            'cost_price'         => 10000,
            'status'             => 'active',
        ]);

        // Đơn pending hôm nay → vào placed (sold = 5).
        $pending = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'total'          => '75000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $pending->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 5,
        ]);

        // Đơn cancelled hôm nay → KHÔNG tính.
        $cancelled = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'cancelled',
            'payment_status' => 'pending',
            'total'          => '150000',
            'created_at'     => now(),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $cancelled->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 30,
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/products/today');

        $response->assertOk();
        $placed = $response->json('data.products_placed');
        $this->assertCount(1, $placed);
        // Chỉ 5 từ đơn pending, không gồm 30 từ đơn cancelled.
        $this->assertEquals(5.0, $placed[0]['sold']);
    }

    // ─── D. Stock-In (Khai báo nhập kho buổi sáng) ─────────────────────────────

    /**
     * suggestions: TB 7 ngày = tổng bán / 7, gợi ý = round(avg), và
     * suggested_total trong meta = tổng gợi ý các SKU.
     */
    public function test_stock_in_suggestions_computes_7day_average(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();
        $farm->products()->attach($product->id, ['cost_price' => 8000]);

        // Đã giao 14kg trong 7 ngày qua → TB = 2kg/ngày → gợi ý = 2.
        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '210000',
            'created_at'     => now()->subDays(2),
            'delivered_at'   => now()->subDays(2),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 14,
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/stock-in/suggestions');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('product_id', $product->id);
        $this->assertNotNull($row);
        $this->assertEquals(2.0, $row['avg_daily_sold']);
        $this->assertEquals(2, $row['suggested_qty']);
        $this->assertEquals(15000.0, $row['price']);
        $this->assertEquals(7, $row['window_days']);
        // Hạn dùng mặc định = ngày + 5.
        $this->assertEquals(
            \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->addDays(5)->toDateString(),
            $row['suggested_expire_date']
        );
        $this->assertEquals(2, $response->json('meta.suggested_total'));
    }

    /**
     * sold_out_yesterday=true khi hôm qua có bán nhưng tồn hiện tại = 0.
     */
    public function test_stock_in_suggestions_flags_sold_out_yesterday(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();
        $farm->products()->attach($product->id, ['cost_price' => 8000]);

        $order = ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => 'delivered',
            'payment_status' => 'completed',
            'total'          => '75000',
            'created_at'     => now()->subDay(),
            'delivered_at'   => \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->subDay()->setTime(10, 0),
        ]);
        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'farm_id'    => $farm->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => 5,
        ]);
        // Không tạo batch active nào → stock = 0.

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/stock-in/suggestions');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('product_id', $product->id);
        $this->assertTrue($row['sold_out_yesterday']);
        // Cháy hàng & tồn 0 → gợi ý ít nhất bằng phần bán hôm qua (5).
        $this->assertGreaterThanOrEqual(5, $row['suggested_qty']);
    }

    /**
     * importBatch: tạo nhiều batch một lần, tự gắn farm_id + expire_date mặc định.
     */
    public function test_stock_in_batch_creates_multiple_batches(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $p1 = $this->makeProduct();
        $p2 = $this->makeProduct();
        $farm->products()->attach($p1->id, ['cost_price' => 8000]);
        $farm->products()->attach($p2->id, ['cost_price' => 12000]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->postJson('/api/farm/stock-in/batch', [
                'items' => [
                    ['product_id' => $p1->id, 'quantity' => 30],
                    ['product_id' => $p2->id, 'quantity' => 15],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertEquals(2, $response->json('meta.count'));

        $this->assertDatabaseHas('farm_stock_batches', [
            'farm_id'     => $farm->id,
            'product_id'  => $p1->id,
            'quantity_in' => 30,
            'cost_price'  => 8000, // lấy từ pivot khi không truyền
            'status'      => 'active',
        ]);
        // expire_date tự tính = batch_date + 5 ngày.
        $batch = FarmStockBatch::where('product_id', $p1->id)->first();
        $this->assertEquals(
            \Carbon\Carbon::parse($batch->batch_date)->addDays(5)->toDateString(),
            \Carbon\Carbon::parse($batch->expire_date)->toDateString()
        );
    }

    /**
     * importBatch: SKU không thuộc farm (không có pivot) → 403, không tạo batch nào.
     */
    public function test_stock_in_batch_rejects_unowned_product(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $owned   = $this->makeProduct();
        $foreign = $this->makeProduct(); // KHÔNG attach vào farm
        $farm->products()->attach($owned->id, ['cost_price' => 8000]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->postJson('/api/farm/stock-in/batch', [
                'items' => [
                    ['product_id' => $owned->id, 'quantity' => 10],
                    ['product_id' => $foreign->id, 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(403)->assertJson(['error' => true]);
        // Transaction chưa chạy → không batch nào được tạo.
        $this->assertDatabaseMissing('farm_stock_batches', ['product_id' => $owned->id]);
    }

    /**
     * importBatch: client GỬI expire_date thủ công → lô lưu đúng ngày đó
     * (KHÔNG bị ghi đè bằng batch_date + 5). Phủ nhánh user-supplied của STOCK-02.
     */
    public function test_stock_in_batch_uses_client_supplied_expire_date(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $p = $this->makeProduct();
        $farm->products()->attach($p->id, ['cost_price' => 8000]);

        $expire = \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->addDays(12)->toDateString();

        $response = $this->withHeaders($this->authHeader($customer))
            ->postJson('/api/farm/stock-in/batch', [
                'items' => [
                    ['product_id' => $p->id, 'quantity' => 10, 'expire_date' => $expire],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.0.expire_date', $expire);

        $batch = FarmStockBatch::where('product_id', $p->id)->first();
        $this->assertEquals(
            $expire,
            \Carbon\Carbon::parse($batch->expire_date)->toDateString()
        );
    }

    /**
     * import (per-SKU): có expire_date → lưu đúng; không có → null (lô không hạn).
     */
    public function test_per_sku_import_respects_expire_date(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $p = $this->makeProduct();
        $farm->products()->attach($p->id, ['cost_price' => 8000]);

        $expire = \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->addDays(8)->toDateString();

        // Có expire_date (route: POST /farm/inventory/import, product_id trong body)
        $this->withHeaders($this->authHeader($customer))
            ->postJson("/api/farm/inventory/import", [
                'product_id'  => $p->id,
                'quantity'    => 7,
                'expire_date' => $expire,
                'note'        => 'lô có hạn',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.expire_date', $expire);

        // Không truyền expire_date → null
        $this->withHeaders($this->authHeader($customer))
            ->postJson("/api/farm/inventory/import", [
                'product_id' => $p->id,
                'quantity'   => 3,
                'note'       => 'lô không hạn',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.expire_date', null);
    }

    /**
     * GET /farm/inventory?view=batches trả expire_date từng lô đúng định dạng
     * YYYY-MM-DD — màn "Lô hàng" của FE đọc field này (STOCK-02).
     */
    public function test_inventory_batches_view_returns_expire_date(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $p = $this->makeProduct();
        $farm->products()->attach($p->id, ['cost_price' => 8000]);

        $expire = \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->addDays(4)->toDateString();
        FarmStockBatch::create([
            'farm_id'       => $farm->id,
            'product_id'    => $p->id,
            'batch_date'    => \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString(),
            'quantity_in'   => 9,
            'quantity_sold' => 0,
            'cost_price'    => 8000,
            'expire_date'   => $expire,
            'status'        => 'active',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson("/api/farm/inventory?view=batches&product_id={$p->id}&status=active");

        $response->assertOk()
            ->assertJsonPath('meta.view', 'batches')
            ->assertJsonPath('data.0.product_id', $p->id)
            ->assertJsonPath('data.0.expire_date', $expire);
    }

    // ─── E. Payouts (Công nợ & Thanh toán) ──────────────────────────────────────

    /**
     * /farm/me trả commission_rate (FE cần để hiển thị "Phí Vietponics x%").
     */
    public function test_farm_me_returns_commission_rate(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/me');

        $response->assertOk()
            ->assertJsonPath('data.commission_rate', 0.1)
            ->assertJsonPath('data.payment_cycle', 'monthly');
    }

    /**
     * /farm/payouts trả breakdown: commission_amount = gross*(1-rate),
     * net_estimated = gross*rate + adjustment, expected_pay_date cho đợt chưa trả.
     */
    public function test_payouts_list_includes_commission_breakdown(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();

        FarmPayout::create([
            'farm_id'       => $farm->id,
            'period_start'  => '2026-05-13',
            'period_end'    => '2026-05-19',
            'total_sold'    => 187,
            'gross_revenue' => 1_000_000,
            'adjustment'    => 0,
            'net_payout'    => 1_000_000, // draft chưa áp phí (theo cron hiện tại)
            'status'        => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/payouts');

        $response->assertOk()
            ->assertJsonPath('data.0.commission_rate', 0.1)
            // rate=0.1 → phí Vietponics = 90% = 900.000 (JSON serialize số nguyên)
            ->assertJsonPath('data.0.commission_amount', 900000)
            // net ước tính = gross*rate + adj = 100.000
            ->assertJsonPath('data.0.net_estimated', 100000)
            // period_end 19/05 → dự kiến trả 20/05
            ->assertJsonPath('data.0.expected_pay_date', '2026-05-20');
    }

    /**
     * Đợt đã paid không có expected_pay_date (đã trả rồi).
     */
    public function test_paid_payout_has_no_expected_pay_date(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();

        FarmPayout::create([
            'farm_id'       => $farm->id,
            'period_start'  => '2026-05-06',
            'period_end'    => '2026-05-12',
            'total_sold'    => 100,
            'gross_revenue' => 500_000,
            'adjustment'    => 0,
            'net_payout'    => 50_000,
            'status'        => 'paid',
            'paid_at'       => \Carbon\Carbon::parse('2026-05-13 09:00:00'),
            'payment_method'=> 'bank_transfer',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson('/api/farm/payouts');

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('data.0.expected_pay_date', null);
    }

    /**
     * /farm/payouts/{id} liệt kê từng đơn đóng góp (delivering/delivered) trong kỳ,
     * gom theo order_id với qty + gross (cost_price_snapshot * quantity).
     */
    public function test_payout_detail_lists_contributing_orders(): void
    {
        [$customer, $farm] = $this->makeFarmPartner();
        $product = $this->makeProduct();

        $payout = FarmPayout::create([
            'farm_id'       => $farm->id,
            'period_start'  => '2026-05-13',
            'period_end'    => '2026-05-19',
            'total_sold'    => 10,
            'gross_revenue' => 100_000,
            'adjustment'    => 0,
            'net_payout'    => 100_000,
            'status'        => 'draft',
        ]);

        // Đơn delivered trong kỳ → được tính.
        $inPeriod = ZaloOrder::create([
            'customer_id' => $customer->id,
            'status'      => 'delivered',
            'total'       => '200000',
            'created_at'  => '2026-05-15 03:00:00',
        ]);
        ZaloOrderItem::create([
            'order_id'            => $inPeriod->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 10,
            'cost_price_snapshot' => '10000',
        ]);

        // Đơn pending → KHÔNG tính (tránh số ảo).
        $pending = ZaloOrder::create([
            'customer_id' => $customer->id,
            'status'      => 'pending',
            'total'       => '50000',
            'created_at'  => '2026-05-16 03:00:00',
        ]);
        ZaloOrderItem::create([
            'order_id'            => $pending->id,
            'product_id'          => $product->id,
            'farm_id'             => $farm->id,
            'name'                => $product->name,
            'price'               => '20000',
            'quantity'            => 5,
            'cost_price_snapshot' => '10000',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson("/api/farm/payouts/{$payout->id}");

        $response->assertOk()
            ->assertJsonPath('data.payout.id', $payout->id)
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.order_id', $inPeriod->id)
            ->assertJsonPath('data.orders.0.qty', 10)
            ->assertJsonPath('data.orders.0.gross', 100000);
    }

    /**
     * Không xem được payout của farm khác → 404 (không leak dữ liệu).
     */
    public function test_payout_detail_404_for_other_farms_payout(): void
    {
        [$customer] = $this->makeFarmPartner();

        // Payout thuộc một farm khác.
        $otherFarm = Farm::create([
            'code'            => 'OTHER' . uniqid(),
            'name'            => 'Other Farm',
            'commission_rate' => 0.15,
            'payment_cycle'   => 'weekly',
            'is_active'       => true,
            'approved_at'     => now(),
        ]);
        $otherPayout = FarmPayout::create([
            'farm_id'       => $otherFarm->id,
            'period_start'  => '2026-05-13',
            'period_end'    => '2026-05-19',
            'total_sold'    => 5,
            'gross_revenue' => 50_000,
            'adjustment'    => 0,
            'net_payout'    => 50_000,
            'status'        => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeader($customer))
            ->getJson("/api/farm/payouts/{$otherPayout->id}");

        $response->assertStatus(404)->assertJson(['error' => true]);
    }

    // ─── ROLE-04: phân quyền payout (chỉ owner) ────────────────────────────────

    /**
     * Owner xem được danh sách payout (chống regression sau khi thêm gate owner).
     */
    public function test_owner_can_access_payouts(): void
    {
        [$owner, $farm] = $this->makeFarmPartner();

        FarmPayout::create([
            'farm_id'       => $farm->id,
            'period_start'  => '2026-05-13',
            'period_end'    => '2026-05-19',
            'total_sold'    => 100,
            'gross_revenue' => 1_000_000,
            'adjustment'    => 0,
            'net_payout'    => 1_000_000,
            'status'        => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/farm/payouts');

        $response->assertOk()->assertJsonPath('error', false);
    }

    /**
     * Staff KHÔNG xem được danh sách payout → 403 (dữ liệu tài chính chỉ owner).
     */
    public function test_staff_cannot_access_payouts_list(): void
    {
        [, $farm] = $this->makeFarmPartner();
        $staff = $this->makeFarmStaff($farm);

        // Có payout của chính farm → nếu chưa gate, staff sẽ nhận 200 + data.
        FarmPayout::create([
            'farm_id'       => $farm->id,
            'period_start'  => '2026-05-13',
            'period_end'    => '2026-05-19',
            'total_sold'    => 100,
            'gross_revenue' => 1_000_000,
            'adjustment'    => 0,
            'net_payout'    => 1_000_000,
            'status'        => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeader($staff))
            ->getJson('/api/farm/payouts');

        $response->assertStatus(403)
            ->assertJson([
                'error'   => true,
                'message' => 'Bạn không có quyền xem mục này',
            ]);
    }

    /**
     * Staff KHÔNG xem được chi tiết payout của farm mình → 403.
     */
    public function test_staff_cannot_access_payout_detail(): void
    {
        [, $farm] = $this->makeFarmPartner();
        $staff = $this->makeFarmStaff($farm);

        $payout = FarmPayout::create([
            'farm_id'       => $farm->id,
            'period_start'  => '2026-05-13',
            'period_end'    => '2026-05-19',
            'total_sold'    => 100,
            'gross_revenue' => 1_000_000,
            'adjustment'    => 0,
            'net_payout'    => 1_000_000,
            'status'        => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeader($staff))
            ->getJson("/api/farm/payouts/{$payout->id}");

        $response->assertStatus(403)
            ->assertJson([
                'error'   => true,
                'message' => 'Bạn không có quyền xem mục này',
            ]);
    }

    /**
     * Staff của Farm B gọi payout detail của Farm A → 403 (gate owner chạy TRƯỚC
     * scope farm, nên trả 403 chứ không phải 404 — không leak cả sự tồn tại).
     */
    public function test_staff_of_other_farm_cannot_access_payout_detail(): void
    {
        // Farm A + payout của A.
        [, $farmA] = $this->makeFarmPartner();
        $payoutA = FarmPayout::create([
            'farm_id'       => $farmA->id,
            'period_start'  => '2026-05-13',
            'period_end'    => '2026-05-19',
            'total_sold'    => 100,
            'gross_revenue' => 1_000_000,
            'adjustment'    => 0,
            'net_payout'    => 1_000_000,
            'status'        => 'draft',
        ]);

        // Farm B + staff của B.
        [, $farmB] = $this->makeFarmPartner();
        $staffB = $this->makeFarmStaff($farmB);

        $response = $this->withHeaders($this->authHeader($staffB))
            ->getJson("/api/farm/payouts/{$payoutA->id}");

        $response->assertStatus(403)
            ->assertJson([
                'error'   => true,
                'message' => 'Bạn không có quyền xem mục này',
            ]);
    }
}
