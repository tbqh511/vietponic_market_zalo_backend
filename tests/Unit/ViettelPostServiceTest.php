<?php

namespace Tests\Unit;

use App\Services\ViettelPostService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ViettelPostServiceTest extends TestCase
{
    private ViettelPostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new ViettelPostService();
    }

    // ── getToken ──────────────────────────────────────────────────────────────

    public function test_get_token_calls_login_then_ownerconnect_on_cold_cache(): void
    {
        Http::fake([
            '*/v2/user/Login'       => Http::response(['data' => ['token' => 'short_token_abc']], 200),
            '*/v2/user/ownerconnect' => Http::response(['data' => ['token' => 'long_token_xyz']], 200),
        ]);

        $token = $this->service->getToken();

        $this->assertSame('long_token_xyz', $token);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'Login'));
        Http::assertSent(
            fn ($req) => str_contains($req->url(), 'ownerconnect')
                && ($req->header('Token')[0] ?? null) === 'short_token_abc'
        );
    }

    public function test_get_token_returns_cached_token_without_api_call(): void
    {
        Cache::put('vtp_long_term_token', 'cached_token_123', now()->addDays(300));

        Http::fake();

        $token = $this->service->getToken();

        $this->assertSame('cached_token_123', $token);
        Http::assertNothingSent();
    }

    public function test_get_token_is_cached_after_first_fetch(): void
    {
        Http::fake([
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'short']], 200),
            '*/v2/user/ownerconnect' => Http::response(['data' => ['token' => 'long_first']], 200),
        ]);

        $first  = $this->service->getToken();
        $second = $this->service->getToken();

        $this->assertSame($first, $second);
        $this->assertSame('long_first', $first);
        Http::assertSentCount(2); // Login + ownerconnect, once total
    }

    // ── forceRefreshToken ─────────────────────────────────────────────────────

    public function test_force_refresh_clears_cache_and_refetches(): void
    {
        Cache::put('vtp_long_term_token', 'old_token', now()->addDays(300));

        Http::fake([
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'short']], 200),
            '*/v2/user/ownerconnect' => Http::response(['data' => ['token' => 'new_token']], 200),
        ]);

        $this->service->forceRefreshToken();

        $this->assertSame('new_token', $this->service->getToken());
    }

    // ── callApi ───────────────────────────────────────────────────────────────

    public function test_call_api_retries_once_on_401_then_succeeds(): void
    {
        Cache::put('vtp_long_term_token', 'stale_token', now()->addDays(300));

        Http::fake([
            '*/v2/some/endpoint'     => Http::sequence()
                ->push([], 401)
                ->push(['result' => 'ok'], 200),
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'short']], 200),
            '*/v2/user/ownerconnect' => Http::response(['data' => ['token' => 'fresh_token']], 200),
        ]);

        $result = $this->service->callApi('post', '/v2/some/endpoint', []);

        $this->assertSame(['result' => 'ok'], $result);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'Login'));
    }

    public function test_call_api_throws_after_two_consecutive_401(): void
    {
        Cache::put('vtp_long_term_token', 'stale_token', now()->addDays(300));

        Http::fake([
            '*/v2/some/endpoint'     => Http::response([], 401),
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'short']], 200),
            '*/v2/user/ownerconnect' => Http::response(['data' => ['token' => 'new_token']], 200),
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $this->service->callApi('post', '/v2/some/endpoint', []);
    }

    // ── fetchLongTermToken error handling ─────────────────────────────────────

    public function test_fetch_long_term_token_throws_if_login_response_missing_token(): void
    {
        Http::fake([
            '*/v2/user/Login' => Http::response(['data' => []], 200),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->getToken();
    }

    public function test_fetch_long_term_token_throws_if_ownerconnect_response_missing_token(): void
    {
        Http::fake([
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'short']], 200),
            '*/v2/user/ownerconnect' => Http::response(['data' => []], 200),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->getToken();
    }

    // ── getDaysUntilTokenExpiry ───────────────────────────────────────────────

    public function test_get_days_until_token_expiry_returns_null_when_no_cache(): void
    {
        $this->assertNull($this->service->getDaysUntilTokenExpiry());
    }

    public function test_get_days_until_token_expiry_returns_correct_days(): void
    {
        $expiry = now()->addDays(45);
        Cache::put('vtp_long_term_token', 'some_token', $expiry);
        Cache::put('vtp_long_term_token_expiry', $expiry->timestamp, $expiry);

        $days = $this->service->getDaysUntilTokenExpiry();

        $this->assertNotNull($days);
        $this->assertEqualsWithDelta(45, $days, 1);
    }
}
