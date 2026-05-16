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
        $this->service = new ViettelPostService();
    }

    // ── getToken ──────────────────────────────────────────────────────────────

    public function test_get_token_returns_token_from_api(): void
    {
        Cache::forget('vtp_token');

        Http::fake([
            '*/v2/user/Login' => Http::response([
                'data' => ['token' => 'test-vtp-token-abc'],
            ], 200),
        ]);

        $token = $this->service->getToken();

        $this->assertSame('test-vtp-token-abc', $token);
    }

    public function test_get_token_is_cached(): void
    {
        Cache::forget('vtp_token');

        Http::fake([
            '*/v2/user/Login' => Http::sequence()
                ->push(['data' => ['token' => 'first-token']], 200)
                ->push(['data' => ['token' => 'second-token']], 200),
        ]);

        $first  = $this->service->getToken();
        $second = $this->service->getToken();

        // Chỉ gọi API 1 lần, lần 2 phải từ cache
        $this->assertSame($first, $second);
        $this->assertSame('first-token', $first);
        Http::assertSentCount(1);
    }

    public function test_get_token_throws_when_token_missing(): void
    {
        Cache::forget('vtp_token');

        Http::fake([
            '*/v2/user/Login' => Http::response(['data' => []], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->getToken();
    }

    // ── listProvinces ─────────────────────────────────────────────────────────

    public function test_list_provinces_normalizes_response(): void
    {
        Cache::forget('vtp_token');
        Cache::forget('vtp_provinces');

        Http::fake([
            '*/v2/user/Login'         => Http::response(['data' => ['token' => 'tok']], 200),
            '*/v2/categories/listProvince' => Http::response([
                'data' => [
                    ['PROVINCE_ID' => 1, 'PROVINCE_CODE' => 'HN', 'PROVINCE_NAME' => 'Hà Nội', 'STATUS' => 1],
                    ['PROVINCE_ID' => 2, 'PROVINCE_CODE' => 'HCM', 'PROVINCE_NAME' => 'TP.HCM', 'STATUS' => 1],
                ],
            ], 200),
        ]);

        $provinces = $this->service->listProvinces();

        $this->assertCount(2, $provinces);
        $this->assertSame(1, $provinces[0]['id']);
        $this->assertSame('Hà Nội', $provinces[0]['name']);
        $this->assertSame('HN', $provinces[0]['code']);
    }

    // ── getPriceAll ───────────────────────────────────────────────────────────

    public function test_get_price_all_normalizes_services(): void
    {
        Cache::forget('vtp_token');

        Http::fake([
            '*/v2/user/Login'      => Http::response(['data' => ['token' => 'tok']], 200),
            '*/v2/order/getPriceAll' => Http::response([
                'data' => [
                    [
                        'MA_DV_CHINH'         => 'LCOD',
                        'TEN_DICHVU'          => 'Chuyển phát tiêu chuẩn',
                        'GIA_CUOC'            => 35000,
                        'VAT'                 => 3500,
                        'MONEY_TOTAL'         => 38500,
                        'THOI_GIAN_PHAT'      => '2-3 ngày',
                        'TRONG_LUONG_QUY_DOI' => 500,
                    ],
                ],
            ], 200),
        ]);

        $services = $this->service->getPriceAll(
            receiverProvinceId: 1,
            receiverDistrictId: 10,
            productWeight: 300,
            productPrice: 100000,
        );

        $this->assertCount(1, $services);
        $this->assertSame('LCOD', $services[0]['service_code']);
        $this->assertSame(38500, $services[0]['total_fee']);
        $this->assertSame('2-3 ngày', $services[0]['kpi_ht']);
    }

    public function test_get_price_all_sends_cod_money_collection(): void
    {
        Cache::forget('vtp_token');

        Http::fake([
            '*/v2/user/Login'        => Http::response(['data' => ['token' => 'tok']], 200),
            '*/v2/order/getPriceAll' => Http::response(['data' => []], 200),
        ]);

        $this->service->getPriceAll(
            receiverProvinceId: 1,
            receiverDistrictId: 10,
            productWeight: 500,
            productPrice: 200000,
            isCod: true,
        );

        Http::assertSent(function ($req) {
            if (!str_contains($req->url(), 'getPriceAll')) {
                return false;
            }
            $body = json_decode($req->body(), true) ?? [];
            return ($body['MONEY_COLLECTION'] ?? null) === 200000;
        });
    }
}
