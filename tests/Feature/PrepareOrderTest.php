<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * PrepareOrderTest — POST /api/prepare-order (zalo.jwt):
 * trả MAC đúng công thức + echo orderData; cần JWT; validate amount.
 */
class PrepareOrderTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private function authHeaders(Customer $customer): array
    {
        $token = JWTAuth::claims(['customer_id' => $customer->id])->fromUser($customer);
        return ['Authorization' => "Bearer {$token}"];
    }

    private function expectedMac(array $params): string
    {
        ksort($params);
        $dataMac = collect($params)
            ->map(fn ($value, $key) => $key . '=' . (is_array($value) || $value === null ? json_encode($value) : $value))
            ->implode('&');
        return hash_hmac('sha256', $dataMac, env('ZALO_CHECK_OUT_SECRET'));
    }

    public function test_prepare_order_returns_correct_mac_and_echoes_data(): void
    {
        $customer = $this->makeCustomer();

        $payload = [
            'amount'    => 150000,
            'desc'      => 'Thanh toan don hang #1',
            'item'      => [['id' => 1, 'amount' => 150000]],
            'extradata' => 'order:1',
            'method'    => 'BANK_SANDBOX',
        ];

        $res = $this->withHeaders($this->authHeaders($customer))
            ->postJson('/api/prepare-order', $payload);

        $res->assertOk()->assertJson(['error' => false]);

        $expected = $this->expectedMac([
            'amount'    => 150000,
            'desc'      => 'Thanh toan don hang #1',
            'item'      => [['id' => 1, 'amount' => 150000]],
            'extradata' => 'order:1',
            'method'    => 'BANK_SANDBOX',
        ]);
        $this->assertSame($expected, $res->json('mac'));
        $this->assertSame('BANK_SANDBOX', $res->json('orderData.method'));
        $this->assertSame(150000, $res->json('orderData.amount'));
    }

    public function test_prepare_order_requires_jwt(): void
    {
        $res = $this->postJson('/api/prepare-order', [
            'amount' => 1000,
            'desc'   => 'x',
            'item'   => [['id' => 1, 'amount' => 1000]],
        ]);

        $res->assertStatus(401);
    }

    public function test_prepare_order_validates_amount_required(): void
    {
        $customer = $this->makeCustomer();

        $res = $this->withHeaders($this->authHeaders($customer))
            ->postJson('/api/prepare-order', [
                'desc' => 'x',
                'item' => [['id' => 1, 'amount' => 1000]],
            ]);

        $res->assertStatus(422);
    }
}
