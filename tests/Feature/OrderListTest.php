<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * OrderListTest — GET /api/orders + /api/orders/{id} (zalo.jwt).
 * Chỉ trả đơn của chính customer; cần JWT; show đơn người khác → 404.
 */
class OrderListTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private function authHeaders(Customer $customer): array
    {
        $token = JWTAuth::claims(['customer_id' => $customer->id])->fromUser($customer);
        return ['Authorization' => "Bearer {$token}"];
    }

    private function makeOrder(Customer $customer, string $status = 'pending'): ZaloOrder
    {
        return ZaloOrder::create([
            'customer_id'    => $customer->id,
            'status'         => $status,
            'payment_status' => 'cod',
            'payment_method' => 'COD',
            'total'          => 100000,
            'created_at'     => now(),
        ]);
    }

    public function test_orders_requires_jwt(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
    }

    public function test_list_returns_only_own_orders(): void
    {
        $a = $this->makeCustomer();
        $b = $this->makeCustomer();
        $this->makeOrder($a);
        $this->makeOrder($a);
        $this->makeOrder($b);

        $res = $this->withHeaders($this->authHeaders($a))->getJson('/api/orders');

        $res->assertOk()->assertJson(['error' => false]);
        $this->assertCount(2, $res->json('data'));
    }

    public function test_list_filters_by_status(): void
    {
        $a = $this->makeCustomer();
        $this->makeOrder($a, 'pending');
        $this->makeOrder($a, 'delivered');

        $res = $this->withHeaders($this->authHeaders($a))->getJson('/api/orders?status=delivered');

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('delivered', $res->json('data.0.status'));
    }

    public function test_show_returns_own_order(): void
    {
        $a = $this->makeCustomer();
        $order = $this->makeOrder($a);

        $res = $this->withHeaders($this->authHeaders($a))->getJson("/api/orders/{$order->id}");

        $res->assertOk()->assertJson(['error' => false]);
        $this->assertSame($order->id, $res->json('data.id'));
    }

    public function test_show_other_customers_order_returns_404(): void
    {
        $a = $this->makeCustomer();
        $b = $this->makeCustomer();
        $order = $this->makeOrder($b);

        $res = $this->withHeaders($this->authHeaders($a))->getJson("/api/orders/{$order->id}");

        $res->assertStatus(404);
    }
}
