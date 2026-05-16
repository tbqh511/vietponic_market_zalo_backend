<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShippingEstimateTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private ZaloProduct $product;
    private VtpProvince $province;
    private VtpDistrict $district;
    private VtpWard $ward;

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
            'length'      => 25,
            'width'       => 18,
            'height'      => 8,
            'stock'       => 100,
        ]);

        $this->province = VtpProvince::create(['id' => 1, 'name' => 'TP.HCM', 'status' => 1]);
        $this->district = VtpDistrict::create(['id' => 10, 'province_id' => 1, 'name' => 'Quận 1', 'status' => 1]);
        $this->ward     = VtpWard::create(['id' => 100, 'district_id' => 10, 'name' => 'Phường Bến Nghé', 'status' => 1]);

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

    // ── Success cases ─────────────────────────────────────────────────────────

    public function test_estimate_returns_services_from_vtp(): void
    {
        Http::fake([
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'tok']], 200),
            '*/v2/order/getPriceAll' => Http::response([
                'data' => [[
                    'MA_DV_CHINH'    => 'LCOD',
                    'TEN_DICHVU'     => 'Chuyển phát tiêu chuẩn',
                    'GIA_CUOC'       => 35000,
                    'VAT'            => 0,
                    'MONEY_TOTAL'    => 35000,
                    'THOI_GIAN_PHAT' => '2-3 ngày',
                ]],
            ], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/shipping/estimate', [
                'items'                => [['product_id' => 1, 'quantity' => 2]],
                'receiver_province_id' => 1,
                'receiver_district_id' => 10,
                'receiver_ward_id'     => 100,
                'product_price'        => 60000,
            ]);

        $response->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.0.service_code', 'LCOD')
            ->assertJsonPath('data.0.total_fee', 35000);
    }

    public function test_estimate_returns_fallback_when_vtp_times_out(): void
    {
        Http::fake([
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'tok']], 200),
            '*/v2/order/getPriceAll' => Http::response(null, 500),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/shipping/estimate', [
                'items'                => [['product_id' => 1, 'quantity' => 1]],
                'receiver_province_id' => 1,
                'receiver_district_id' => 10,
                'receiver_ward_id'     => 100,
                'product_price'        => 30000,
            ]);

        $response->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('fallback', true);

        // Phải có ít nhất 1 dịch vụ fallback
        $this->assertNotEmpty($response->json('data'));
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_estimate_requires_ward_id(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/shipping/estimate', [
                'items'                => [['product_id' => 1, 'quantity' => 1]],
                'receiver_province_id' => 1,
                'receiver_district_id' => 10,
                // thiếu ward_id
                'product_price'        => 30000,
            ]);

        $response->assertStatus(422);
    }

    public function test_estimate_requires_auth(): void
    {
        $response = $this->postJson('/api/shipping/estimate', [
            'items'                => [['product_id' => 1, 'quantity' => 1]],
            'receiver_province_id' => 1,
            'receiver_district_id' => 10,
            'receiver_ward_id'     => 100,
            'product_price'        => 30000,
        ]);

        $response->assertStatus(401);
    }

    public function test_estimate_rejects_invalid_province(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/shipping/estimate', [
                'items'                => [['product_id' => 1, 'quantity' => 1]],
                'receiver_province_id' => 9999, // không tồn tại
                'receiver_district_id' => 10,
                'receiver_ward_id'     => 100,
                'product_price'        => 30000,
            ]);

        $response->assertStatus(422);
    }

    // ── Location endpoints ────────────────────────────────────────────────────

    public function test_provinces_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/locations/provinces');
        $response->assertOk()->assertJsonPath('error', false);
    }

    public function test_districts_requires_province_id(): void
    {
        $response = $this->getJson('/api/locations/districts');
        $response->assertStatus(422);
    }

    public function test_districts_returns_filtered_list(): void
    {
        $response = $this->getJson('/api/locations/districts?province_id=1');
        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Quận 1');
    }
}
