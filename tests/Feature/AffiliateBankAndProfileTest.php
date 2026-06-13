<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * AFF-04: updateBank (3 nhánh: vắng key / "" / giá trị mới)
 *         GET /affiliate/me (shape response)
 *         GET /affiliate/referrals (mask + phân trang)
 */
class AffiliateBankAndProfileTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);
        Setting::updateOrCreate(['type' => 'affiliate_auto_approve'], ['data' => '1']);
    }

    private function authHeaders(Customer $customer): array
    {
        $token = JWTAuth::fromUser($customer);
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function makeAffiliate(array $overrides = []): Customer
    {
        return $this->makeApprovedAffiliate('TST' . uniqid(), $overrides);
    }

    // ── updateBank — 3 nhánh ────────────────────────────────────────────────

    /** Key vắng mặt → field giữ nguyên */
    public function test_absent_key_does_not_overwrite(): void
    {
        $affiliate = $this->makeAffiliate([
            'affiliate_bank_name'    => 'Vietcombank',
            'affiliate_bank_account' => '1234567890',
            'affiliate_bank_holder'  => 'Nguyen Van A',
        ]);

        // Chỉ gửi bank_name — bank_account và bank_holder không có trong body
        $this->withHeaders($this->authHeaders($affiliate))
            ->patchJson('/api/affiliate/bank', ['bank_name' => 'Techcombank'])
            ->assertOk();

        $affiliate->refresh();
        $this->assertSame('Techcombank', $affiliate->affiliate_bank_name);
        $this->assertSame('1234567890', $affiliate->affiliate_bank_account);
        $this->assertSame('Nguyen Van A', $affiliate->affiliate_bank_holder);
    }

    /** Gửi "" → field bị xoá (null) */
    public function test_empty_string_clears_field(): void
    {
        $affiliate = $this->makeAffiliate([
            'affiliate_bank_name'    => 'Vietcombank',
            'affiliate_bank_account' => '1234567890',
            'affiliate_bank_holder'  => 'Nguyen Van A',
        ]);

        $this->withHeaders($this->authHeaders($affiliate))
            ->patchJson('/api/affiliate/bank', [
                'bank_name'    => '',
                'bank_account' => '',
                'bank_holder'  => '',
            ])
            ->assertOk();

        $affiliate->refresh();
        $this->assertNull($affiliate->affiliate_bank_name);
        $this->assertNull($affiliate->affiliate_bank_account);
        $this->assertNull($affiliate->affiliate_bank_holder);
    }

    /** Gửi giá trị mới → DB cập nhật đúng */
    public function test_new_value_is_saved(): void
    {
        $affiliate = $this->makeAffiliate();

        $this->withHeaders($this->authHeaders($affiliate))
            ->patchJson('/api/affiliate/bank', [
                'bank_name'    => 'BIDV',
                'bank_account' => '9876543210',
                'bank_holder'  => 'Tran Thi B',
            ])
            ->assertOk();

        $affiliate->refresh();
        $this->assertSame('BIDV', $affiliate->affiliate_bank_name);
        $this->assertSame('9876543210', $affiliate->affiliate_bank_account);
        $this->assertSame('Tran Thi B', $affiliate->affiliate_bank_holder);
    }

    // ── GET /affiliate/me — shape ────────────────────────────────────────────

    public function test_me_returns_correct_shape(): void
    {
        $affiliate = $this->makeAffiliate();

        $response = $this->withHeaders($this->authHeaders($affiliate))
            ->getJson('/api/affiliate/me')
            ->assertOk();

        $data = $response->json('data');
        foreach ([
            'is_registered', 'affiliate_code', 'affiliate_status',
            'approved_at', 'share_url',
            'bank_name', 'bank_account', 'bank_holder',
            'commission_rate', 'referrals_count',
            'commission_stats', 'balance', 'locked',
        ] as $key) {
            $this->assertArrayHasKey($key, $data, "Key '$key' vắng trong response data");
        }

        $this->assertTrue($data['is_registered']);
        $this->assertIsNumeric($data['commission_rate']);
        $this->assertIsArray($data['commission_stats']);
        foreach (['pending', 'confirmed', 'paid', 'cancelled'] as $s) {
            $this->assertArrayHasKey($s, $data['commission_stats']);
        }
    }

    // ── GET /affiliate/referrals — mask + phân trang ─────────────────────────

    public function test_referrals_masks_name_and_mobile(): void
    {
        $affiliate = $this->makeAffiliate();

        // Tạo 1 khách được giới thiệu (lưu vào DB để endpoint trả về)
        $this->makeCustomer([
            'referred_by_customer_id' => $affiliate->id,
            'name'   => 'Nguyen Van C',
            'mobile' => '0901234578',
        ]);

        $response = $this->withHeaders($this->authHeaders($affiliate))
            ->getJson('/api/affiliate/referrals')
            ->assertOk();

        $items = $response->json('data');
        $this->assertCount(1, $items);

        $item = $items[0];
        // Không được lộ tên/số thô
        $this->assertArrayNotHasKey('mobile', $item, 'Field mobile thô không được xuất hiện');
        $this->assertArrayHasKey('mobile_masked', $item);

        // Tên phải được mask (có *)
        $this->assertStringContainsString('*', $item['name']);
        // Mobile mask: 3 số đầu + *** + 2 số cuối
        $this->assertStringContainsString('*', $item['mobile_masked']);
        $this->assertStringStartsWith('090', $item['mobile_masked']);
        $this->assertStringEndsWith('78', $item['mobile_masked']);

        // Không được chứa tên gốc
        $this->assertStringNotContainsString('Nguyen Van C', $item['name']);
    }

    public function test_referrals_pagination_meta(): void
    {
        $affiliate = $this->makeAffiliate();

        // Tạo 7 khách để với per_page=5 có 2 trang
        for ($i = 0; $i < 7; $i++) {
            $this->makeCustomer(['referred_by_customer_id' => $affiliate->id]);
        }

        $response = $this->withHeaders($this->authHeaders($affiliate))
            ->getJson('/api/affiliate/referrals?per_page=5')
            ->assertOk();

        $meta = $response->json('meta');
        $this->assertArrayHasKey('total', $meta);
        $this->assertArrayHasKey('per_page', $meta);
        $this->assertArrayHasKey('current_page', $meta);
        $this->assertArrayHasKey('last_page', $meta);

        $this->assertEquals(7, $meta['total']);
        $this->assertEquals(5, $meta['per_page']);
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(2, $meta['last_page']);
    }
}
