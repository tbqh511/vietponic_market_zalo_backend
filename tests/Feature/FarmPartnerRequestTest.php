<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Kiểm tra POST /farm/request-partnership
 * — gửi yêu cầu, guard trùng lặp, guard đã duyệt.
 */
class FarmPartnerRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $attrs = []): Customer
    {
        static $seq = 0;
        $seq++;
        return Customer::create(array_merge([
            'name'        => "Customer {$seq}",
            'firebase_id' => '',
            'email'       => "c{$seq}_" . uniqid() . '@test.local',
            'mobile'      => '09' . str_pad((string)(30000000 + $seq), 8, '0', STR_PAD_LEFT),
            'address'     => '',
            'logintype'   => 'mobile',
            'isActive'    => 1,
        ], $attrs));
    }

    private function jwtHeader(Customer $customer): array
    {
        $token = JWTAuth::fromUser($customer);
        return ['Authorization' => "Bearer {$token}"];
    }

    private function validPayload(): array
    {
        return [
            'name'        => 'Joiley Farm',
            'address'     => 'Đà Lạt, Lâm Đồng',
            'description' => 'Farm rau sạch công nghệ cao.',
        ];
    }

    // ─── A. Gửi yêu cầu thành công ───────────────────────────────────────────

    /** @test */
    public function customer_can_submit_farm_partnership_request(): void
    {
        $customer = $this->makeCustomer();

        $res = $this->postJson(
            '/api/farm/request-partnership',
            $this->validPayload(),
            $this->jwtHeader($customer)
        );

        $res->assertOk()
            ->assertJsonPath('error', false);

        $customer->refresh();
        $this->assertSame('requested', $customer->farm_partner_status);
        $this->assertSame('Joiley Farm', $customer->farm_application_name);
        $this->assertSame('Đà Lạt, Lâm Đồng', $customer->farm_application_address);
        $this->assertSame('Farm rau sạch công nghệ cao.', $customer->farm_application_description);
        $this->assertNotNull($customer->farm_applied_at);
    }

    /** @test */
    public function description_is_optional(): void
    {
        $customer = $this->makeCustomer();

        $res = $this->postJson(
            '/api/farm/request-partnership',
            ['name' => 'Farm ABC', 'address' => 'TP.HCM'],
            $this->jwtHeader($customer)
        );

        $res->assertOk()->assertJsonPath('error', false);
        $this->assertNull($customer->fresh()->farm_application_description);
    }

    // ─── B. Guard trùng lặp ──────────────────────────────────────────────────

    /** @test */
    public function duplicate_request_returns_409(): void
    {
        $customer = $this->makeCustomer(['farm_partner_status' => 'requested']);

        $res = $this->postJson(
            '/api/farm/request-partnership',
            $this->validPayload(),
            $this->jwtHeader($customer)
        );

        $res->assertStatus(409)->assertJsonPath('error', true);
    }

    /** @test */
    public function already_approved_returns_409(): void
    {
        $customer = $this->makeCustomer([
            'role'                => 'farm_partner',
            'farm_partner_status' => 'approved',
        ]);

        $res = $this->postJson(
            '/api/farm/request-partnership',
            $this->validPayload(),
            $this->jwtHeader($customer)
        );

        $res->assertStatus(409)->assertJsonPath('error', true);
    }

    // ─── C. Validation ───────────────────────────────────────────────────────

    /** @test */
    public function name_is_required(): void
    {
        $customer = $this->makeCustomer();

        $res = $this->postJson(
            '/api/farm/request-partnership',
            ['address' => 'Đà Lạt'],
            $this->jwtHeader($customer)
        );

        $res->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function address_is_required(): void
    {
        $customer = $this->makeCustomer();

        $res = $this->postJson(
            '/api/farm/request-partnership',
            ['name' => 'My Farm'],
            $this->jwtHeader($customer)
        );

        $res->assertStatus(422)->assertJsonValidationErrors(['address']);
    }

    // ─── D. Auth ─────────────────────────────────────────────────────────────

    /** @test */
    public function unauthenticated_request_returns_401(): void
    {
        $res = $this->postJson(
            '/api/farm/request-partnership',
            $this->validPayload()
        );

        $res->assertStatus(401);
    }
}
