<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ZaloCustomerPhoneFilterTest — filter SĐT trang Admin "Quản lý Khách hàng"
 * (route zalo-customers.index, middleware auth+checklogin).
 *
 * Yêu cầu: nhập một phần SĐT (vd "3878") khớp được số đầy đủ "84918963878",
 * filter real-time bằng AJAX trả về partial table (không kèm layout).
 *
 * - Partial suffix match: phone=3878 chỉ ra khách có mobile chứa "3878".
 * - Normalize: số lưu có dấu cách/“+” vẫn match theo digit-only.
 * - AJAX partial (?partial=1): trả về đúng bảng, KHÔNG có card-header full page.
 */
class ZaloCustomerPhoneFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'status'      => 1,
            'type'        => 0,
            'permissions' => '',
        ]);
    }

    private function customer(string $name, string $mobile): Customer
    {
        return Customer::create([
            'name'        => $name,
            'mobile'      => $mobile,
            'firebase_id' => 'zalo_' . uniqid(),
            'email'       => uniqid() . '@test.local',
            'address'     => '',
            'logintype'   => 'mobile',
            'isActive'    => 1,
        ]);
    }

    public function test_partial_suffix_matches_full_number(): void
    {
        $this->customer('Chị Hoa', '84918963878');
        $this->customer('Anh Nam', '84912000111');

        $res = $this->actingAs($this->admin())
            ->get(route('zalo-customers.index', ['phone' => '3878']));

        $res->assertOk();
        $res->assertSee('84918963878');
        $res->assertSee('Chị Hoa');
        $res->assertDontSee('Anh Nam');
        $res->assertDontSee('84912000111');
    }

    public function test_normalizes_separators_in_stored_number(): void
    {
        $this->customer('Chú Tư', '+84 988 777 666');

        $res = $this->actingAs($this->admin())
            ->get(route('zalo-customers.index', ['phone' => '777666']));

        $res->assertOk();
        $res->assertSee('Chú Tư');
    }

    public function test_non_digit_input_is_ignored(): void
    {
        // Input toàn ký tự lạ → coi như rỗng, không filter (trả tất cả).
        $this->customer('Chị Hoa', '84918963878');
        $this->customer('Anh Nam', '84912000111');

        $res = $this->actingAs($this->admin())
            ->get(route('zalo-customers.index', ['phone' => '---']));

        $res->assertOk();
        $res->assertSee('Chị Hoa');
        $res->assertSee('Anh Nam');
    }

    public function test_ajax_partial_returns_table_without_layout(): void
    {
        $this->customer('Chị Hoa', '84918963878');

        $res = $this->actingAs($this->admin())
            ->get(route('zalo-customers.index', ['phone' => '3878', 'partial' => 1]));

        $res->assertOk();
        $res->assertSee('84918963878');
        // Card-header "Quản lý Khách hàng" chỉ có ở full page, không có trong partial.
        $res->assertDontSee('Quản lý Khách hàng');
    }
}
