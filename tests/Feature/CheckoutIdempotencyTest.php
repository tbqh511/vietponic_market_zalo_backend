<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloCategory;
use App\Models\ZaloOrder;
use App\Models\ZaloProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * ORDER-08 (B17) — idempotency của POST /checkout + chống TOCTOU.
 *
 * Hợp đồng: double-tap đặt hàng (2 request trùng payload từ cùng khách) chỉ tạo
 * 1 đơn; request thứ 2 trả 201 với duplicated:true + orderId của request đầu.
 *
 * PHPUnit chạy đồng bộ nên không mô phỏng được 2 process song song thật; ở đây
 * test theo THỨ TỰ đúng hợp đồng (request 2 đến SAU khi đơn đầu đã Cache::put).
 * Phần atomic (database lock) đóng window TOCTOU ở runtime production — xem
 * ZaloApiController::store().
 */
class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private ZaloProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = ZaloCategory::create(['id' => 1, 'name' => 'Rau', 'image' => null]);
        $this->product = ZaloProduct::create([
            'id'          => 1,
            'category_id' => $cat->id,
            'name'        => 'Rau muống',
            'price'       => 30000,
            'weight'      => 300,
            'stock'       => 100,
        ]);

        // checkAvailability()/reserveItems() đọc tồn từ farm_stock_batches, không
        // phải cột stock → cần 1 batch active để /checkout không bị 422 hết hàng.
        $owner = Customer::create([
            'name'        => 'Farm Owner',
            'email'       => 'owner@zalo.user',
            'firebase_id' => 'farm-owner-id',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);
        $farm = Farm::create([
            'code'              => 'IDEMP1',
            'name'              => 'Farm Idempotency',
            'owner_customer_id' => $owner->id,
            'address'           => 'Đà Lạt',
            'commission_rate'   => 0.1000,
            'payment_cycle'     => 'monthly',
            'is_active'         => true,
            'approved_at'       => now(),
        ]);
        FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $this->product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => 100,
            'quantity_sold'      => 0,
            'quantity_remaining' => 100, // SQLite fallback (generated column)
            'cost_price'         => 20000,
            'expire_date'        => now()->addDays(10)->toDateString(),
            'status'             => 'active',
        ]);

        $this->customer = Customer::create([
            'name'        => 'Test User',
            'email'       => 'test@zalo.user',
            'firebase_id' => 'test-zalo-id',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($this->customer)];
    }

    private function orderPayload(array $override = []): array
    {
        $qty      = (int) ($override['_qty'] ?? 2);
        $subtotal = $this->product->price * $qty;
        // Đơn "tự đến lấy" để khỏi cần seed địa chỉ VTP — shipping_fee = 0.
        $total = $subtotal;

        return array_replace_recursive([
            'items' => [[
                'product_id' => (string) $this->product->id,
                'name'       => $this->product->name,
                'price'      => (string) $this->product->price,
                'quantity'   => (string) $qty,
                'image'      => '',
                'detail'     => '',
            ]],
            'delivery' => [
                'type'    => 'pickup',
                'address' => 'Cửa hàng chính',
                'name'    => 'Nguyễn Văn A',
                'phone'   => '0912345678',
            ],
            'subtotal'     => (string) $subtotal,
            'shipping_fee' => '0',
            'total'        => (string) $total,
            'note'         => '',
            'created_at'   => now()->toIso8601String(),
        ], collect($override)->except('_qty')->all());
    }

    /**
     * Tái tạo idempotency key giống hệt ZaloApiController::store() để seed cache.
     */
    private function idempotencyKey(array $payload): string
    {
        $itemSig = collect($payload['items'])
            ->map(fn ($i) => $i['product_id'] . ':' . $i['quantity'])
            ->sort()
            ->implode('|');

        return 'zalo_order_dedupe:' . md5(implode(';', [
            $this->customer->id,
            $itemSig,
            (string) $payload['total'],
            strtoupper((string) ($payload['payment_method'] ?? '')),
            $payload['delivery']['type'] ?? '',
        ]));
    }

    // ── 2× checkout trùng → 1 đơn ────────────────────────────────────────────

    public function test_duplicate_checkout_creates_single_order(): void
    {
        $payload = $this->orderPayload();

        $first = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $payload);
        $first->assertCreated();
        $firstOrderId = $first->json('orderId');
        $this->assertNotNull($firstOrderId);

        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $payload);
        $second->assertCreated()
            ->assertJsonPath('duplicated', true)
            ->assertJsonPath('orderId', $firstOrderId);

        // Chỉ 1 đơn được tạo dù gọi 2 lần.
        $this->assertSame(1, ZaloOrder::count());
    }

    // ── TOCTOU: orderId đã cache TRƯỚC khi request 2 chạy → không tạo đơn mới ──

    public function test_cached_order_id_short_circuits_before_creation(): void
    {
        $payload = $this->orderPayload();

        // Mô phỏng "request đầu đã tạo đơn xong + đã Cache::put orderId" (đúng thứ
        // tự add-trước/đọc-sau). Request trùng tiếp theo phải đọc được orderId này.
        $existing = ZaloOrder::create([
            'status'         => 'pending',
            'payment_status' => 'cod',
            'payment_method' => 'COD',
            'subtotal'       => 60000,
            'shipping_fee'   => 0,
            'total'          => 60000,
            'customer_id'    => $this->customer->id,
        ]);
        Cache::put($this->idempotencyKey($payload), $existing->id, now()->addSeconds(90));

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $payload);

        $response->assertCreated()
            ->assertJsonPath('duplicated', true)
            ->assertJsonPath('orderId', $existing->id);

        // Không có đơn thứ 2 — vẫn đúng 1 đơn (đơn seed).
        $this->assertSame(1, ZaloOrder::count());
    }

    // ── Payload khác (qty khác) → key khác → 2 đơn riêng ─────────────────────

    public function test_different_payload_creates_separate_orders(): void
    {
        $first = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload(['_qty' => 2]));
        $first->assertCreated();

        $second = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $this->orderPayload(['_qty' => 3]));
        $second->assertCreated();
        // Payload khác → KHÔNG phải duplicate.
        $this->assertNull($second->json('duplicated'));

        $this->assertSame(2, ZaloOrder::count());
    }
}
