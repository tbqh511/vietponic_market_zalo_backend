<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class ViettelPostService
{
    private string $apiUrl;

    private const TOKEN_CACHE_KEY        = 'vtp_long_term_token';
    private const TOKEN_EXPIRY_CACHE_KEY = 'vtp_long_term_token_expiry';

    public function __construct()
    {
        $this->apiUrl = rtrim(config('viettelpost.api_url'), '/');
    }

    public function getToken(): string
    {
        return Cache::remember(
            self::TOKEN_CACHE_KEY,
            now()->addDays(config('viettelpost.token_ttl_days', 355)),
            function () {
                $token = $this->fetchLongTermToken();
                $ttlDays = config('viettelpost.token_ttl_days', 355);
                Cache::put(self::TOKEN_EXPIRY_CACHE_KEY, now()->addDays($ttlDays)->timestamp, now()->addDays($ttlDays));

                return $token;
            }
        );
    }

    private function fetchLongTermToken(): string
    {
        Log::channel('shipping')->info('VTP: fetching long-term token');

        $credentials = [
            'USERNAME' => config('viettelpost.username'),
            'PASSWORD' => config('viettelpost.password'),
        ];

        try {
            $loginRes = Http::timeout(15)
                ->post("{$this->apiUrl}/v2/user/Login", $credentials)
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::channel('shipping')->error('VTP Login failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $shortToken = $loginRes['data']['token'] ?? null;
        if (!$shortToken) {
            throw new \RuntimeException('VTP Login: token không có trong response');
        }

        try {
            $ownerRes = Http::timeout(15)
                ->withHeaders(['Token' => $shortToken])
                ->post("{$this->apiUrl}/v2/user/ownerconnect", $credentials)
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::channel('shipping')->error('VTP ownerconnect failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $longToken = $ownerRes['data']['token'] ?? null;
        if (!$longToken) {
            throw new \RuntimeException('VTP ownerconnect: token không có trong response');
        }

        Log::channel('shipping')->info('VTP: long-term token fetched successfully');

        return $longToken;
    }

    public function callApi(string $method, string $path, array $payload = []): array
    {
        try {
            return Http::timeout(15)
                ->baseUrl($this->apiUrl)
                ->withHeaders(['Token' => $this->getToken()])
                ->$method($path, $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            if ($e->response->status() === 401) {
                Log::channel('shipping')->warning('VTP 401 on first attempt, refreshing token and retrying');
                $this->forceRefreshToken();

                try {
                    return Http::timeout(15)
                        ->baseUrl($this->apiUrl)
                        ->withHeaders(['Token' => $this->getToken()])
                        ->$method($path, $payload)
                        ->throw()
                        ->json();
                } catch (\Throwable $retryEx) {
                    Log::channel('shipping')->error('VTP retry after 401 failed', ['error' => $retryEx->getMessage()]);
                    throw $retryEx;
                }
            }

            Log::channel('shipping')->error('VTP API error', ['status' => $e->response->status(), 'path' => $path]);
            throw $e;
        }
    }

    public function forceRefreshToken(): void
    {
        Log::channel('shipping')->warning('VTP: force-refreshing long-term token');
        Cache::forget(self::TOKEN_CACHE_KEY);
        Cache::forget(self::TOKEN_EXPIRY_CACHE_KEY);
        $this->getToken();
    }

    public function getDaysUntilTokenExpiry(): ?int
    {
        if (!Cache::has(self::TOKEN_CACHE_KEY)) {
            return null;
        }

        $expiryTimestamp = Cache::get(self::TOKEN_EXPIRY_CACHE_KEY);

        if ($expiryTimestamp === null) {
            return null;
        }

        $secondsLeft = $expiryTimestamp - now()->timestamp;

        if ($secondsLeft <= 0) {
            return 0;
        }

        return (int) ($secondsLeft / 86400);
    }

    public function getPriceAll(array $payload): array
    {
        $response = $this->callApi('post', '/v2/order/getPriceAll', $payload);

        return $response['data'] ?? [];
    }

    /**
     * Trả về mảng province: ['id', 'code', 'name', 'status']
     * @deprecated Dùng listProvincesV3() để lấy đơn vị hành chính mới sau sáp nhập tỉnh 7/2025
     */
    public function listProvinces(): array
    {
        return Cache::remember('vtp_provinces', now()->addDays(30), function () {
            $res = $this->callApi('get', '/v2/categories/listProvince');

            return $this->normalizeProvinces($res['data'] ?? []);
        });
    }

    /**
     * Trả về mảng province v3: ['id', 'code', 'name', 'status']
     * Dùng đơn vị hành chính mới sau sáp nhập tỉnh tháng 7/2025.
     */
    public function listProvincesV3(): array
    {
        return Cache::remember('vtp_provinces_v3', now()->addDays(30), function () {
            $res = $this->callApi('get', '/v3/categories/listProvinceNew');

            return $this->normalizeProvinces($res['data'] ?? []);
        });
    }

    /**
     * Trả về mảng district: ['id', 'province_id', 'code', 'name', 'status']
     * Giữ lại để getPriceAll vẫn có district_id cho shipping estimate.
     */
    public function listDistricts(int $provinceId): array
    {
        return Cache::remember("vtp_districts_{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            $res = $this->callApi('get', '/v2/categories/listDistrict', ['provinceId' => $provinceId]);

            return $this->normalizeDistricts($res['data'] ?? [], $provinceId);
        });
    }

    /**
     * Trả về mảng ward: ['id', 'district_id', 'name', 'status']
     * @deprecated Dùng listWardsV3() — v3 query theo province_id thay vì district_id
     */
    public function listWards(int $districtId): array
    {
        return Cache::remember("vtp_wards_{$districtId}", now()->addDays(7), function () use ($districtId) {
            $res = $this->callApi('get', '/v2/categories/listWards', ['districtId' => $districtId]);

            return $this->normalizeWards($res['data'] ?? [], $districtId);
        });
    }

    /**
     * Trả về mảng ward v3: ['id', 'province_id', 'district_id', 'name', 'status']
     * V3 bỏ cấp quận/huyện — query theo provinceId. Ward vẫn có district_id để dùng với getPriceAll.
     */
    public function listWardsV3(int $provinceId): array
    {
        return Cache::remember("vtp_wards_v3_{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            $res = $this->callApi('get', '/v3/categories/listWardsNew', ['provinceId' => $provinceId]);

            return $this->normalizeWardsV3($res['data'] ?? [], $provinceId);
        });
    }

    // ── internal normalizers ────────────────────────────────────────────────

    private function normalizeProvinces(array $raw): array
    {
        return collect($raw)->map(fn ($p) => [
            'id'     => (int) ($p['PROVINCE_ID'] ?? $p['id'] ?? 0),
            'code'   => $p['PROVINCE_CODE'] ?? $p['code'] ?? null,
            'name'   => $p['PROVINCE_NAME'] ?? $p['name'] ?? '',
            'status' => (int) ($p['STATUS'] ?? $p['status'] ?? 1),
        ])->filter(fn ($p) => $p['id'] > 0)->values()->all();
    }

    private function normalizeDistricts(array $raw, int $provinceId): array
    {
        return collect($raw)->map(fn ($d) => [
            'id'          => (int) ($d['DISTRICT_ID'] ?? $d['id'] ?? 0),
            'province_id' => $provinceId,
            'code'        => $d['DISTRICT_VALUE'] ?? $d['code'] ?? null,
            'name'        => $d['DISTRICT_NAME'] ?? $d['name'] ?? '',
            'status'      => (int) ($d['STATUS'] ?? $d['status'] ?? 1),
        ])->filter(fn ($d) => $d['id'] > 0)->values()->all();
    }

    private function normalizeWards(array $raw, int $districtId): array
    {
        return collect($raw)->map(fn ($w) => [
            'id'          => (int) ($w['WARDS_ID'] ?? $w['id'] ?? 0),
            'district_id' => $districtId,
            'name'        => $w['WARDS_NAME'] ?? $w['name'] ?? '',
            'status'      => (int) ($w['STATUS'] ?? $w['status'] ?? 1),
        ])->filter(fn ($w) => $w['id'] > 0)->values()->all();
    }

    private function normalizeWardsV3(array $raw, int $provinceId): array
    {
        return collect($raw)->map(fn ($w) => [
            'id'          => (int) ($w['WARDS_ID'] ?? $w['id'] ?? 0),
            'province_id' => $provinceId,
            'district_id' => isset($w['DISTRICT_ID']) && $w['DISTRICT_ID'] ? (int) $w['DISTRICT_ID']
                           : (isset($w['district_id']) && $w['district_id'] ? (int) $w['district_id'] : null),
            'name'        => $w['WARDS_NAME'] ?? $w['name'] ?? '',
            'status'      => (int) ($w['STATUS'] ?? $w['status'] ?? 1),
        ])->filter(fn ($w) => $w['id'] > 0)->values()->all();
    }
}
