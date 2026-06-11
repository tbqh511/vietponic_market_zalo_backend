<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AuthenticateTest — POST /api/authenticate (public, đổi Zalo access_token lấy JWT).
 *
 * Phủ AUTH-01/03/04 (batch B10):
 *  - Tạo customer mới: ưu tiên name/avatar client gửi (SDK getUserInfo) hơn Graph API.
 *  - AUTH-03: thiếu cả client lẫn Graph name → placeholder thống nhất "Khách Zalo".
 *  - AUTH-04: gọi lại cùng firebase_id → UPDATE chứ KHÔNG nhân bản (idempotent).
 *  - Guard: tên placeholder/rỗng KHÔNG được che tên thật đã có trong DB.
 *
 * Graph API được Http::fake — không gọi mạng thật, không phụ thuộc secret thật.
 */
class AuthenticateTest extends TestCase
{
    use RefreshDatabase;

    /** Giả lập Graph API trả profile Zalo (me?fields=id,name,picture). */
    private function fakeGraphProfile(string $zaloId, string $name = '', ?string $avatarUrl = null): void
    {
        $body = ['id' => $zaloId, 'name' => $name];
        if ($avatarUrl !== null) {
            $body['picture'] = ['data' => ['url' => $avatarUrl]];
        }
        Http::fake([
            'graph.zalo.me/*' => Http::response($body, 200),
        ]);
    }

    private function authenticate(array $payload)
    {
        return $this->postJson('/api/authenticate', $payload);
    }

    /** Case 1 — client gửi name/avatar thật (đã cấp scope.userInfo) → ưu tiên client. */
    public function test_creates_customer_with_client_name_over_graph(): void
    {
        $this->fakeGraphProfile('zalo_1', name: 'Graph Name', avatarUrl: 'https://graph/avatar.jpg');

        $res = $this->authenticate([
            'access_token' => 'tok_1',
            'name'         => 'Nguyễn Văn A',
            'avatar'       => 'https://sdk/avatar.jpg',
        ]);

        $res->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.user.name', 'Nguyễn Văn A');

        $this->assertSame(1, Customer::where('firebase_id', 'zalo_1')->count());
        $customer = Customer::where('firebase_id', 'zalo_1')->first();
        $this->assertSame('Nguyễn Văn A', $customer->name);
        // Kiểm GIÁ TRỊ THÔ trong DB — bỏ qua accessor getProfileAttribute (vốn
        // prepend đường dẫn ảnh local, là hành vi legacy BDS ngoài phạm vi B10).
        $this->assertSame('https://sdk/avatar.jpg', $customer->getRawOriginal('profile'));
        $this->assertNotEmpty($res->json('data.token'));
    }

    /** Case 2 (AUTH-03) — từ chối quyền: không có client name + Graph name rỗng → "Khách Zalo". */
    public function test_creates_customer_with_unified_placeholder_when_no_name(): void
    {
        $this->fakeGraphProfile('zalo_declined', name: '');

        $res = $this->authenticate(['access_token' => 'tok_declined']);

        $res->assertOk()->assertJsonPath('data.user.name', 'Khách Zalo');
        $this->assertSame('Khách Zalo', Customer::where('firebase_id', 'zalo_declined')->first()->name);
    }

    /** Case 3 (AUTH-04) — gọi 2 lần cùng firebase_id → chỉ 1 row, không nhân bản. */
    public function test_re_authenticate_same_zalo_id_does_not_duplicate(): void
    {
        $this->fakeGraphProfile('zalo_dup', name: 'Người Dùng', avatarUrl: 'https://g/a.jpg');

        $first  = $this->authenticate(['access_token' => 'tok_a']);
        $second = $this->authenticate(['access_token' => 'tok_b']);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(1, Customer::where('firebase_id', 'zalo_dup')->count());
        $this->assertSame(
            $first->json('data.user.id'),
            $second->json('data.user.id'),
            'Cùng Zalo id phải map về cùng 1 customer'
        );
    }

    /** Case 4 — re-auth: tên thật ở lần 2 đè placeholder ở lần 1 (cùng row). */
    public function test_re_authenticate_updates_placeholder_with_real_name(): void
    {
        // Lần 1: từ chối quyền → "Khách Zalo".
        $this->fakeGraphProfile('zalo_upd', name: '');
        $this->authenticate(['access_token' => 'tok_1']);
        $this->assertSame('Khách Zalo', Customer::where('firebase_id', 'zalo_upd')->first()->name);

        // Lần 2: đã cấp quyền → client gửi tên thật.
        $this->fakeGraphProfile('zalo_upd', name: '');
        $this->authenticate(['access_token' => 'tok_2', 'name' => 'Trần Thị B']);

        $this->assertSame(1, Customer::where('firebase_id', 'zalo_upd')->count());
        $this->assertSame('Trần Thị B', Customer::where('firebase_id', 'zalo_upd')->first()->name);
    }

    /** Case 5 — guard: name placeholder/rỗng KHÔNG che tên thật đã có trong DB. */
    public function test_placeholder_does_not_overwrite_existing_real_name(): void
    {
        $existing = Customer::create([
            'name'        => 'Lê Văn C',
            'email'       => 'zalo_keep@zalo.user',
            'firebase_id' => 'zalo_keep',
            'logintype'   => 'zalo',
            'isActive'    => 1,
        ]);

        $this->fakeGraphProfile('zalo_keep', name: '');
        // Client cố gửi placeholder (mô phỏng gọi API thô bỏ qua guard FE).
        $this->authenticate(['access_token' => 'tok_keep', 'name' => 'Khách Zalo']);

        $existing->refresh();
        $this->assertSame('Lê Văn C', $existing->name);
        $this->assertSame(1, Customer::where('firebase_id', 'zalo_keep')->count());
    }
}
