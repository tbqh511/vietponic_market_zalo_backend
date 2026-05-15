<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\StockMovement;
use App\Models\ZaloCategory;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Models\ZaloUnit;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductUnitTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    public function test_units_table_is_seeded_with_default_codes(): void
    {
        $codes = ZaloUnit::pluck('code')->all();

        foreach (['bo', 'kg', 'g', 'hop', 'goi', 'chai', 'lit', 'ml', 'cai'] as $expected) {
            $this->assertContains($expected, $codes, "Missing seeded unit: {$expected}");
        }

        $this->assertSame('g',     ZaloUnit::where('code', 'bo')->value('system_unit_type'));
        $this->assertSame('ml',    ZaloUnit::where('code', 'chai')->value('system_unit_type'));
        $this->assertSame('piece', ZaloUnit::where('code', 'cai')->value('system_unit_type'));
    }

    public function test_existing_products_default_to_piece_with_factor_one(): void
    {
        ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);

        $product = ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Sản phẩm cũ', 'price' => 10000,
        ]);

        $product->refresh();
        $this->assertSame('piece', $product->system_unit);
        $this->assertEquals(1, (float) $product->conversion_factor);
        $this->assertNull($product->unit_id);
    }

    public function test_product_with_bundle_unit_computes_system_total_on_order(): void
    {
        ZaloCategory::create(['id' => 1, 'name' => 'Rau thơm', 'image' => null]);
        $bo = ZaloUnit::where('code', 'bo')->first();

        $product = ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Húng quế', 'price' => 8000,
            'unit_id' => $bo->id, 'system_unit' => 'g', 'conversion_factor' => 100,
        ]);

        $order = ZaloOrder::create([
            'customer_id' => 1, 'total' => 24000, 'payment_status' => 'pending',
        ]);

        $quantity = 3;
        $item = ZaloOrderItem::create([
            'order_id'          => $order->id,
            'product_id'        => $product->id,
            'name'              => $product->name,
            'price'             => $product->price,
            'quantity'          => $quantity,
            'unit_label'        => $bo->label,
            'system_unit'       => $product->system_unit,
            'conversion_factor' => $product->conversion_factor,
            'system_total'      => $quantity * (float) $product->conversion_factor,
        ]);

        $item->refresh();
        $this->assertSame('bó', $item->unit_label);
        $this->assertSame('g', $item->system_unit);
        $this->assertEquals(100, (float) $item->conversion_factor);
        $this->assertEquals(300, (float) $item->system_total);
    }

    public function test_box_of_tomato_uses_product_specific_factor(): void
    {
        ZaloCategory::create(['id' => 2, 'name' => 'Quả', 'image' => null]);
        $hop = ZaloUnit::where('code', 'hop')->first();

        $tomato = ZaloProduct::create([
            'id' => 2, 'category_id' => 2, 'name' => 'Cà chua bi',
            'price' => 35000, 'unit_id' => $hop->id, 'system_unit' => 'g',
            'conversion_factor' => 200,
        ]);
        $cucumber = ZaloProduct::create([
            'id' => 3, 'category_id' => 2, 'name' => 'Dưa leo',
            'price' => 25000, 'unit_id' => $hop->id, 'system_unit' => 'g',
            'conversion_factor' => 500,
        ]);

        $this->assertEquals(200, (float) $tomato->conversion_factor);
        $this->assertEquals(500, (float) $cucumber->conversion_factor);
        $this->assertSame($hop->id, $tomato->unit->id);
        $this->assertSame($hop->id, $cucumber->unit->id);
    }

    public function test_liquid_product_uses_ml_system_unit(): void
    {
        ZaloCategory::create(['id' => 3, 'name' => 'Dinh dưỡng', 'image' => null]);
        $chai = ZaloUnit::where('code', 'chai')->first();

        $solution = ZaloProduct::create([
            'id' => 4, 'category_id' => 3, 'name' => 'Dung dịch thủy canh A',
            'price' => 120000, 'unit_id' => $chai->id, 'system_unit' => 'ml',
            'conversion_factor' => 1000,
        ]);

        $this->assertSame('ml', $solution->system_unit);
        $this->assertSame('ml', $solution->unit->system_unit_type);
        $this->assertEquals(1000, (float) $solution->conversion_factor);
    }

    public function test_products_api_exposes_unit_fields(): void
    {
        ZaloCategory::create(['id' => 1, 'name' => 'Rau thơm', 'image' => null]);
        $bo = ZaloUnit::where('code', 'bo')->first();
        ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Húng quế', 'price' => 8000,
            'unit_id' => $bo->id, 'system_unit' => 'g', 'conversion_factor' => 100,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('data.0.unit_id', $bo->id)
            ->assertJsonPath('data.0.unit_label', 'bó')
            ->assertJsonPath('data.0.system_unit', 'g')
            ->assertJsonPath('data.0.conversion_factor', 100);
    }

    public function test_products_api_returns_null_unit_for_legacy_products(): void
    {
        ZaloCategory::create(['id' => 1, 'name' => 'Khác', 'image' => null]);
        ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Sản phẩm cũ', 'price' => 10000,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('data.0.unit_id', null)
            ->assertJsonPath('data.0.unit_label', null)
            ->assertJsonPath('data.0.system_unit', 'piece')
            ->assertJsonPath('data.0.conversion_factor', 1);
    }

    public function test_checkout_snapshots_unit_info_into_order_items(): void
    {
        ZaloCategory::create(['id' => 1, 'name' => 'Rau thơm', 'image' => null]);
        $bo = ZaloUnit::where('code', 'bo')->first();
        $product = ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Húng quế',
            'price' => 8000, 'stock' => 50, 'stock_reserved' => 0, 'reorder_point' => 5,
            'unit_id' => $bo->id, 'system_unit' => 'g', 'conversion_factor' => 100,
        ]);

        $customer = $this->makeCustomer();
        $token    = JWTAuth::fromUser($customer);

        $payload = [
            'items' => [[
                'product_id' => (string) $product->id,
                'name'       => $product->name,
                'price'      => (string) $product->price,
                'quantity'   => '3',
                'image'      => '',
                'detail'     => '',
            ]],
            'delivery' => [
                'type' => 'shipping', 'address' => 'Đà Lạt',
                'name' => 'A', 'phone' => '0900000000', 'station_id' => null,
            ],
            'total'      => '24000',
            'note'       => '',
            'created_at' => now()->toIso8601String(),
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/checkout', $payload);

        $response->assertCreated();

        $item = ZaloOrderItem::where('order_id', $response->json('orderId'))->first();
        $this->assertNotNull($item);
        $this->assertSame('bó', $item->unit_label);
        $this->assertSame('g', $item->system_unit);
        $this->assertEquals(100, (float) $item->conversion_factor);
        $this->assertEquals(300, (float) $item->system_total);
    }

    public function test_stock_report_aggregates_by_system_unit(): void
    {
        ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);
        ZaloCategory::create(['id' => 2, 'name' => 'Dinh dưỡng', 'image' => null]);
        ZaloCategory::create(['id' => 3, 'name' => 'Khác', 'image' => null]);

        $bo   = ZaloUnit::where('code', 'bo')->first();
        $chai = ZaloUnit::where('code', 'chai')->first();

        // Bundle of greens: 1 bó = 100g
        $greens = ZaloProduct::create([
            'id' => 10, 'category_id' => 1, 'name' => 'Rau muống',
            'price' => 8000, 'stock' => 0, 'stock_reserved' => 0, 'reorder_point' => 5,
            'unit_id' => $bo->id, 'system_unit' => 'g', 'conversion_factor' => 100,
        ]);

        // Bottle of liquid: 1 chai = 500ml
        $liquid = ZaloProduct::create([
            'id' => 11, 'category_id' => 2, 'name' => 'Dung dịch B',
            'price' => 50000, 'stock' => 0, 'stock_reserved' => 0, 'reorder_point' => 1,
            'unit_id' => $chai->id, 'system_unit' => 'ml', 'conversion_factor' => 500,
        ]);

        // Legacy product: piece, factor=1
        $legacy = ZaloProduct::create([
            'id' => 12, 'category_id' => 3, 'name' => 'Sản phẩm cũ',
            'price' => 10000, 'stock' => 0, 'stock_reserved' => 0, 'reorder_point' => 1,
        ]);

        $now = now();

        // Greens: import 30 bó (= 3000g = 3kg), export 5 bó (= 500g)
        StockMovement::create([
            'product_id' => $greens->id, 'movement_type' => 'import',
            'quantity_change' => 30, 'quantity_before' => 0, 'quantity_after' => 30,
            'created_at' => $now,
        ]);
        StockMovement::create([
            'product_id' => $greens->id, 'movement_type' => 'export',
            'quantity_change' => -5, 'quantity_before' => 30, 'quantity_after' => 25,
            'created_at' => $now,
        ]);

        // Liquid: import 4 chai (= 2000ml = 2l)
        StockMovement::create([
            'product_id' => $liquid->id, 'movement_type' => 'import',
            'quantity_change' => 4, 'quantity_before' => 0, 'quantity_after' => 4,
            'created_at' => $now,
        ]);

        // Legacy: import 7 cái
        StockMovement::create([
            'product_id' => $legacy->id, 'movement_type' => 'import',
            'quantity_change' => 7, 'quantity_before' => 0, 'quantity_after' => 7,
            'created_at' => $now,
        ]);

        $report = app(StockService::class)->getReport(null, null);

        // Per-product rows include system_unit fields
        $greensRow = collect($report['products'])->firstWhere('product_id', $greens->id);
        $this->assertSame('bó', $greensRow['unit_label']);
        $this->assertSame('g', $greensRow['system_unit']);
        $this->assertEquals(100, $greensRow['conversion_factor']);
        $this->assertEquals(3000, $greensRow['imports_system']);
        $this->assertEquals(500, $greensRow['exports_system']);
        $this->assertEquals(2500, $greensRow['net_change_system']); // (30-5)*100

        // System totals grouped by system_unit
        $byUnit = collect($report['system_totals'])->keyBy('system_unit');
        $this->assertEquals(3000, $byUnit['g']['imports']);
        $this->assertEquals(500,  $byUnit['g']['exports']);
        $this->assertEquals(2500, $byUnit['g']['net_change']);

        $this->assertEquals(2000, $byUnit['ml']['imports']);
        $this->assertEquals(0,    $byUnit['ml']['exports']);

        $this->assertEquals(7, $byUnit['piece']['imports']);
    }

    public function test_format_system_total_renders_kg_and_l_above_threshold(): void
    {
        $this->assertSame('3,00 kg', ZaloUnit::formatSystemTotal(3000, 'g'));
        $this->assertSame('500 g',   ZaloUnit::formatSystemTotal(500, 'g'));
        $this->assertSame('2,00 l',  ZaloUnit::formatSystemTotal(2000, 'ml'));
        $this->assertSame('250 ml',  ZaloUnit::formatSystemTotal(250, 'ml'));
        $this->assertSame('7 cái',   ZaloUnit::formatSystemTotal(7, 'piece'));
    }

    public function test_checkout_does_not_trust_client_unit_payload(): void
    {
        ZaloCategory::create(['id' => 1, 'name' => 'Rau thơm', 'image' => null]);
        $bo = ZaloUnit::where('code', 'bo')->first();
        $product = ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'Húng quế',
            'price' => 8000, 'stock' => 50, 'stock_reserved' => 0, 'reorder_point' => 5,
            'unit_id' => $bo->id, 'system_unit' => 'g', 'conversion_factor' => 100,
        ]);

        $customer = $this->makeCustomer();
        $token    = JWTAuth::fromUser($customer);

        $payload = [
            'items' => [[
                'product_id'        => (string) $product->id,
                'name'              => $product->name,
                'price'             => (string) $product->price,
                'quantity'          => '2',
                'image'             => '',
                'detail'             => '',
                // Client tries to inject a forged conversion factor — must be ignored.
                'unit_label'        => 'kg',
                'system_unit'       => 'g',
                'conversion_factor' => '9999',
            ]],
            'delivery' => [
                'type' => 'shipping', 'address' => 'Đà Lạt',
                'name' => 'A', 'phone' => '0900000000', 'station_id' => null,
            ],
            'total'      => '16000',
            'note'       => '',
            'created_at' => now()->toIso8601String(),
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/checkout', $payload);

        $response->assertCreated();

        $item = ZaloOrderItem::where('order_id', $response->json('orderId'))->first();
        $this->assertSame('bó', $item->unit_label, 'unit_label must come from DB, not client');
        $this->assertEquals(100, (float) $item->conversion_factor, 'factor must come from DB');
        $this->assertEquals(200, (float) $item->system_total);
    }
}
