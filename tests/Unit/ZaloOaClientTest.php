<?php

namespace Tests\Unit;

use App\Models\ZaloOaToken;
use App\Services\ZaloOaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Token lifecycle của ZaloOaClient: dùng lại token còn hạn, refresh khi hết hạn và
 * lưu refresh_token MỚI (Zalo đổi refresh_token mỗi lần). + normalizePhone về 84.
 */
class ZaloOaClientTest extends TestCase
{
    use RefreshDatabase;

    private ZaloOaClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.zalo.oa_id'         => 'test_oa',
            'services.zalo.oa_secret_key' => 'test_secret',
        ]);
        $this->client = new ZaloOaClient();
    }

    public function test_returns_cached_token_when_fresh_without_refresh(): void
    {
        ZaloOaToken::create([
            'access_token'  => 'fresh_access',
            'refresh_token' => 'refresh_1',
            'expires_at'    => now()->addHours(10),
        ]);

        Http::fake();

        $this->assertSame('fresh_access', $this->client->getAccessToken());
        Http::assertNothingSent();
    }

    public function test_refreshes_and_persists_new_refresh_token_when_expired(): void
    {
        ZaloOaToken::create([
            'access_token'  => 'old_access',
            'refresh_token' => 'refresh_old',
            'expires_at'    => now()->subMinute(),
        ]);

        Http::fake([
            'oauth.zaloapp.com/*' => Http::response([
                'access_token'  => 'new_access',
                'refresh_token' => 'refresh_new',
                'expires_in'    => 90000,
            ], 200),
        ]);

        $token = $this->client->getAccessToken();

        $this->assertSame('new_access', $token);

        $row = ZaloOaToken::first();
        $this->assertSame('new_access', $row->access_token);
        $this->assertSame('refresh_new', $row->refresh_token, 'phải lưu refresh_token mới');
        $this->assertTrue($row->expires_at->isFuture());

        Http::assertSent(fn ($req) => str_contains($req->url(), 'oauth.zaloapp.com'));
    }

    public function test_refreshes_when_token_within_threshold(): void
    {
        ZaloOaToken::create([
            'access_token'  => 'soon_expiring',
            'refresh_token' => 'refresh_old',
            'expires_at'    => now()->addMinute(), // < ngưỡng 5 phút
        ]);

        Http::fake([
            'oauth.zaloapp.com/*' => Http::response([
                'access_token'  => 'refreshed_access',
                'refresh_token' => 'refresh_new',
                'expires_in'    => 90000,
            ], 200),
        ]);

        $this->assertSame('refreshed_access', $this->client->getAccessToken());
        Http::assertSent(fn ($req) => str_contains($req->url(), 'oauth.zaloapp.com'));
    }

    /**
     * @dataProvider phoneProvider
     */
    public function test_normalize_phone(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->client->normalizePhone($input));
    }

    public static function phoneProvider(): array
    {
        return [
            ['0905123456', '84905123456'],
            ['84905123456', '84905123456'],
            ['+84905123456', '84905123456'],
            ['090 512 3456', '84905123456'],
        ];
    }
}
