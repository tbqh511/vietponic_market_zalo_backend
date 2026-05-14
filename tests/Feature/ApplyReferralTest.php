<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApplyReferralTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
    }

    private function authHeaders(Customer $customer): array
    {
        $token = JWTAuth::fromUser($customer);
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_applies_referral_for_new_customer(): void
    {
        $referrer = $this->makeApprovedAffiliate('ABCD2345');
        $newCustomer = $this->makeCustomer();

        $response = $this->withHeaders($this->authHeaders($newCustomer))
            ->postJson('/api/affiliate/apply-referral', ['ref_code' => 'ABCD2345']);

        $response->assertOk();
        $this->assertEquals($referrer->id, $newCustomer->fresh()->referred_by_customer_id);
    }

    public function test_rejects_when_customer_already_referred(): void
    {
        $a = $this->makeApprovedAffiliate('CODEA001');
        $this->makeApprovedAffiliate('CODEB002');
        $c = $this->makeCustomer(['referred_by_customer_id' => $a->id]);

        $this->withHeaders($this->authHeaders($c))
            ->postJson('/api/affiliate/apply-referral', ['ref_code' => 'CODEB002'])
            ->assertStatus(409);

        $this->assertEquals($a->id, $c->fresh()->referred_by_customer_id);
    }

    public function test_rejects_self_referral(): void
    {
        $c = $this->makeApprovedAffiliate('SELF2345');

        $this->withHeaders($this->authHeaders($c))
            ->postJson('/api/affiliate/apply-referral', ['ref_code' => 'SELF2345'])
            ->assertStatus(422);

        $this->assertNull($c->fresh()->referred_by_customer_id);
    }

    public function test_rejects_when_customer_has_prior_orders(): void
    {
        $this->makeApprovedAffiliate('XYZA2345');
        $customer = $this->makeCustomer();
        ZaloOrder::create([
            'customer_id' => $customer->id, 'status' => 'pending',
            'payment_status' => 'cod', 'total' => 50000, 'created_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($customer))
            ->postJson('/api/affiliate/apply-referral', ['ref_code' => 'XYZA2345'])
            ->assertStatus(409);
    }

    public function test_rejects_when_ref_code_not_approved(): void
    {
        $this->makeCustomer([
            'affiliate_code' => 'PEND2345',
            'affiliate_status' => 'pending',
        ]);
        $newCustomer = $this->makeCustomer();

        $this->withHeaders($this->authHeaders($newCustomer))
            ->postJson('/api/affiliate/apply-referral', ['ref_code' => 'PEND2345'])
            ->assertStatus(404);
    }
}
