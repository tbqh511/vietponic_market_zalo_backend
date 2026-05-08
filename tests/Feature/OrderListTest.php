<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Tests\ZaloTestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Integration tests cho GET /api/orders (list) và GET /api/orders/{id} (detail).
 *
 * Cả hai endpoint đều được bảo vệ bởi ZaloJwtMiddleware.
 * Tests dùng JWT token thực được tạo bằng JWTAuth::fromUser() để kiểm thử toàn bộ auth stack.
 *
 * Yêu cầu JWT_SECRET trong phpunit.xml.
 */
class OrderListTest extends ZaloTestCase
{
    private Customer $customer;
    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name'        => 'Test Nguyen',
            'firebase_id' => 'zalo_test_user_001',
            'email'       => 'test@zalo.user',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);

        $this->jwtToken = JWTAuth::fromUser($this->customer);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->jwtToken];
    }

    /** Tạo một order thuộc về $this->customer, kèm delivery record */
    private function createOrder(string $status = 'pending', array $extra = []): int
    {
        $orderId = DB::table('zalo_orders')->insertGetId(array_merge([
            'customer_id'    => $this->customer->id,
            'status'         => $status,
            'payment_status' => 'cod',
            'total'          => 150000,
            'note'           => 'Test order',
            'created_at'     => now(),
            'received_at'    => now()->addDays(3),
        ], $extra));

        DB::table('zalo_deliveries')->insert([
            'order_id' => $orderId,
            'type'     => 'shipping',
            'address'  => '123 Test Street, HCM',
            'name'     => 'Test Nguyen',
            'phone'    => '0901234567',
        ]);

        return $orderId;
    }

    // ─── GET /api/orders – Happy path ─────────────────────────────────────────

    /**
     * Authenticated customer nhận được danh sách orders của họ – HTTP 200.
     */
    public function test_authenticated_user_can_list_orders(): void
    {
        $this->createOrder('pending');
        $this->createOrder('confirmed');

        $response = $this->getJson('/api/orders', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['error' => false])
            ->assertJsonCount(2, 'data');
    }

    /**
     * Response data items phải có đúng cấu trúc fields.
     */
    public function test_order_list_items_have_expected_structure(): void
    {
        $this->createOrder('delivering');

        $response = $this->getJson('/api/orders', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'status', 'payment_status', 'total', 'created_at', 'received_at'],
                ],
            ]);
    }

    /**
     * Customer chỉ thấy orders của mình, không thấy orders của customer khác.
     */
    public function test_customer_only_sees_their_own_orders(): void
    {
        $otherCustomer = Customer::create([
            'name'        => 'Other User',
            'firebase_id' => 'zalo_other_user_002',
            'email'       => 'other@zalo.user',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);

        DB::table('zalo_orders')->insert([
            'customer_id'    => $otherCustomer->id,
            'status'         => 'pending',
            'payment_status' => 'cod',
            'total'          => 99000,
            'created_at'     => now(),
        ]);

        $this->createOrder('pending');

        $response = $this->getJson('/api/orders', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Filter ?status=delivering chỉ trả về orders có đúng status đó.
     */
    public function test_status_filter_returns_only_matching_orders(): void
    {
        $this->createOrder('pending');
        $this->createOrder('delivering');
        $this->createOrder('delivered');

        $response = $this->getJson('/api/orders?status=delivering', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'delivering');
    }

    /**
     * Không có orders → trả về array rỗng, không phải 404.
     */
    public function test_empty_order_list_returns_empty_array(): void
    {
        $response = $this->getJson('/api/orders', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['error' => false, 'data' => []]);
    }

    /**
     * Orders được sắp xếp mới nhất trước (DESC by id).
     */
    public function test_orders_are_ordered_newest_first(): void
    {
        $this->createOrder('pending');
        $this->createOrder('confirmed');

        $response = $this->getJson('/api/orders', $this->authHeaders());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(
            $data[1]['id'],
            $data[0]['id'],
            'Đơn hàng mới nhất phải xuất hiện đầu tiên (DESC by id)'
        );
    }

    // ─── GET /api/orders – Auth errors ────────────────────────────────────────

    /**
     * Thiếu Authorization header → 401.
     */
    public function test_missing_auth_header_returns_401(): void
    {
        $this->getJson('/api/orders')
            ->assertStatus(401)
            ->assertJson(['error' => true]);
    }

    /**
     * Bearer token không hợp lệ → 401.
     */
    public function test_invalid_token_returns_401(): void
    {
        $this->getJson('/api/orders', ['Authorization' => 'Bearer not_a_real_jwt'])
            ->assertStatus(401);
    }

    // ─── GET /api/orders/{id} – Happy path ────────────────────────────────────

    /**
     * Authenticated customer lấy được order detail theo ID.
     */
    public function test_authenticated_user_can_get_order_detail(): void
    {
        $orderId = $this->createOrder('confirmed');

        $response = $this->getJson("/api/orders/{$orderId}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['error' => false])
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.status', 'confirmed');
    }

    /**
     * Response bao gồm nested items và delivery.
     */
    public function test_order_detail_includes_items_and_delivery(): void
    {
        $orderId = $this->createOrder('pending');

        DB::table('zalo_order_items')->insert([
            'order_id'   => $orderId,
            'product_id' => 1,
            'name'       => 'Rau cải thủy canh',
            'price'      => 50000,
            'quantity'   => 2,
        ]);

        $response = $this->getJson("/api/orders/{$orderId}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'status', 'total',
                    'items'    => [['id', 'name', 'price', 'quantity']],
                    'delivery' => ['type', 'address', 'name'],
                ],
            ]);
    }

    // ─── GET /api/orders/{id} – Not found / Authorization ────────────────────

    /**
     * Order không tồn tại → 404.
     */
    public function test_nonexistent_order_returns_404(): void
    {
        $this->getJson('/api/orders/99999', $this->authHeaders())
            ->assertStatus(404)
            ->assertJson(['error' => true]);
    }

    /**
     * Customer không thể xem order của customer khác dù có JWT hợp lệ → 404 (info hiding).
     */
    public function test_customer_cannot_view_other_customers_order(): void
    {
        $otherCustomer = Customer::create([
            'name'        => 'Other User',
            'firebase_id' => 'zalo_other_user_003',
            'email'       => 'other2@zalo.user',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);

        $othersOrderId = DB::table('zalo_orders')->insertGetId([
            'customer_id'    => $otherCustomer->id,
            'status'         => 'pending',
            'payment_status' => 'cod',
            'total'          => 80000,
            'created_at'     => now(),
        ]);

        $this->getJson("/api/orders/{$othersOrderId}", $this->authHeaders())
            ->assertStatus(404);
    }

    /**
     * Không có Authorization header trên detail endpoint → 401.
     */
    public function test_order_detail_without_auth_returns_401(): void
    {
        $orderId = $this->createOrder('pending');

        $this->getJson("/api/orders/{$orderId}")
            ->assertStatus(401);
    }
}
