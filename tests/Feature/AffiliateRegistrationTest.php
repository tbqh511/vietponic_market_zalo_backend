<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AffiliateRegistrationTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);
    }

    private function authHeaders(Customer $customer): array
    {
        $token = JWTAuth::fromUser($customer);
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_register_auto_approves_when_setting_enabled(): void
    {
        Setting::updateOrCreate(['type' => 'affiliate_auto_approve'], ['data' => '1']);
        $customer = $this->makeCustomer(['name' => 'A']);

        $response = $this->withHeaders($this->authHeaders($customer))
            ->postJson('/api/affiliate/register');

        $response->assertOk();
        $customer->refresh();
        $this->assertNotNull($customer->affiliate_code);
        $this->assertEquals('approved', $customer->affiliate_status);
        $this->assertNotNull($customer->affiliate_approved_at);
    }

    public function test_register_pends_when_auto_approve_disabled(): void
    {
        Setting::updateOrCreate(['type' => 'affiliate_auto_approve'], ['data' => '0']);
        $customer = $this->makeCustomer(['name' => 'B']);

        $response = $this->withHeaders($this->authHeaders($customer))
            ->postJson('/api/affiliate/register');

        $response->assertOk();
        $customer->refresh();
        $this->assertEquals('pending', $customer->affiliate_status);
        $this->assertNull($customer->affiliate_approved_at);
    }

    public function test_double_register_returns_existing_code(): void
    {
        Setting::updateOrCreate(['type' => 'affiliate_auto_approve'], ['data' => '1']);
        $customer = $this->makeCustomer(['name' => 'C']);

        $this->withHeaders($this->authHeaders($customer))->postJson('/api/affiliate/register')->assertOk();
        $code1 = $customer->fresh()->affiliate_code;

        $this->withHeaders($this->authHeaders($customer))->postJson('/api/affiliate/register')->assertOk();
        $code2 = $customer->fresh()->affiliate_code;

        $this->assertEquals($code1, $code2);
    }

    public function test_returns_404_when_module_disabled(): void
    {
        Setting::where('type', 'affiliate_enabled')->update(['data' => '0']);
        $customer = $this->makeCustomer(['name' => 'D']);

        $this->withHeaders($this->authHeaders($customer))
            ->postJson('/api/affiliate/register')
            ->assertStatus(404);
    }
}
