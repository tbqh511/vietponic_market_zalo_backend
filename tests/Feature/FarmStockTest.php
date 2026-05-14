<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FarmPartner;
use App\Models\StockMovement;
use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FarmStockTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $customer;
    private Customer $farmCustomer;
    private FarmPartner $farmPartner;
    private ZaloProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);

        $this->product = ZaloProduct::create([
            'id'             => 1,
            'category_id'    => $category->id,
            'name'           => 'Rau muống',
            'price'          => 20000,
            'stock'          => 50,
            'stock_reserved' => 5,
            'reorder_point'  => 10,
        ]);

        $this->customer = $this->makeCustomer(['name' => 'Customer thường']);

        $this->farmCustomer = $this->makeCustomer(['name' => 'Farm partner']);
        $this->farmPartner  = FarmPartner::create([
            'customer_id' => $this->farmCustomer->id,
            'status'      => 'active',
            'farm_name'   => 'Farm A',
        ]);
    }

    private function jwtHeaders(Customer $customer): array
    {
        $token = JWTAuth::fromUser($customer);
        return ['Authorization' => 'Bearer ' . $token];
    }

    // ─── Test 1: Customer thường bị từ chối (403) ─────────────────────────────

    public function test_regular_customer_cannot_access_farm_inventory(): void
    {
        $this->withHeaders($this->jwtHeaders($this->customer))
            ->getJson('/api/farm/inventory')
            ->assertStatus(403)
            ->assertJson(['error' => true]);
    }

    // ─── Test 2: Farm-partner hợp lệ xem được danh sách tồn kho ──────────────

    public function test_active_farm_partner_can_list_inventory(): void
    {
        $response = $this->withHeaders($this->jwtHeaders($this->farmCustomer))
            ->getJson('/api/farm/inventory');

        $response->assertOk()
            ->assertJson(['error' => false])
            ->assertJsonPath('data.0.id', $this->product->id)
            ->assertJsonPath('data.0.stock', 50)
            ->assertJsonPath('data.0.stock_available', 45); // 50 - 5 reserved
    }

    // ─── Test 3: Farm-partner nhập kho thành công ─────────────────────────────

    public function test_farm_partner_can_import_stock(): void
    {
        $response = $this->withHeaders($this->jwtHeaders($this->farmCustomer))
            ->postJson("/api/farm/inventory/{$this->product->id}/import", [
                'quantity' => 20,
                'note'     => 'Nhập hàng từ vườn',
            ]);

        $response->assertOk()
            ->assertJson(['error' => false, 'message' => 'Nhập kho thành công'])
            ->assertJsonPath('stock', 70);

        $this->assertDatabaseHas('zalo_products', [
            'id'    => $this->product->id,
            'stock' => 70,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'       => $this->product->id,
            'movement_type'    => 'import',
            'quantity_change'  => 20,
            'quantity_before'  => 50,
            'quantity_after'   => 70,
            'farm_customer_id' => $this->farmCustomer->id,
        ]);
    }

    // ─── Test 4: Farm-partner xuất kho thành công ─────────────────────────────

    public function test_farm_partner_can_export_stock(): void
    {
        $response = $this->withHeaders($this->jwtHeaders($this->farmCustomer))
            ->postJson("/api/farm/inventory/{$this->product->id}/export", [
                'quantity' => 10,
                'note'     => 'Xuất hàng đi giao',
            ]);

        $response->assertOk()
            ->assertJson(['error' => false, 'message' => 'Xuất kho thành công'])
            ->assertJsonPath('stock', 40);

        $this->assertDatabaseHas('zalo_products', [
            'id'    => $this->product->id,
            'stock' => 40,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'       => $this->product->id,
            'movement_type'    => 'export',
            'quantity_change'  => -10,
            'quantity_before'  => 50,
            'quantity_after'   => 40,
            'farm_customer_id' => $this->farmCustomer->id,
        ]);
    }

    // ─── Test 5: Xuất kho vượt quá stock_available → 422 ─────────────────────

    public function test_export_exceeding_available_stock_returns_422(): void
    {
        // stock=50, stock_reserved=5 → stock_available=45
        $this->withHeaders($this->jwtHeaders($this->farmCustomer))
            ->postJson("/api/farm/inventory/{$this->product->id}/export", [
                'quantity' => 46,
                'note'     => 'Xuất quá số lượng',
            ])
            ->assertStatus(422);

        // Đảm bảo stock không thay đổi
        $this->assertDatabaseHas('zalo_products', [
            'id'    => $this->product->id,
            'stock' => 50,
        ]);

        $this->assertEquals(0, StockMovement::where('movement_type', 'export')->count());
    }

    // ─── Test 6: Farm-partner inactive bị từ chối (403) ──────────────────────

    public function test_inactive_farm_partner_cannot_access_farm_inventory(): void
    {
        $this->farmPartner->update(['status' => 'inactive']);

        $this->withHeaders($this->jwtHeaders($this->farmCustomer))
            ->getJson('/api/farm/inventory')
            ->assertStatus(403)
            ->assertJson(['error' => true]);
    }

    // ─── Test 7: Xem lịch sử biến động ───────────────────────────────────────

    public function test_farm_partner_can_view_movement_history(): void
    {
        // Tạo một movement trước
        $this->withHeaders($this->jwtHeaders($this->farmCustomer))
            ->postJson("/api/farm/inventory/{$this->product->id}/import", [
                'quantity' => 5,
                'note'     => 'Import test',
            ])->assertOk();

        $response = $this->withHeaders($this->jwtHeaders($this->farmCustomer))
            ->getJson("/api/farm/inventory/{$this->product->id}/movements");

        $response->assertOk()
            ->assertJson(['error' => false])
            ->assertJsonPath('product.id', $this->product->id);

        $this->assertNotEmpty($response->json('data.data'));
    }
}
