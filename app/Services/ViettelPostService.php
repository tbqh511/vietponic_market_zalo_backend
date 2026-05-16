<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ViettelPostService
{
    private string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('viettelpost.api_url'), '/');
    }

    public function getToken(): string
    {
        return Cache::remember('vtp_token', now()->addHours(20), function () {
            $res = Http::timeout(15)
                ->post("{$this->apiUrl}/v2/user/Login", [
                    'USERNAME' => config('viettelpost.username'),
                    'PASSWORD' => config('viettelpost.password'),
                ])
                ->throw()
                ->json();

            $token = $res['data']['token'] ?? null;

            if (!$token) {
                throw new \RuntimeException('VTP Login: token không có trong response');
            }

            return $token;
        });
    }

    /**
     * Trả về mảng province: ['id', 'code', 'name', 'status']
     */
    public function listProvinces(): array
    {
        return Cache::remember('vtp_provinces', now()->addDays(30), function () {
            $res = Http::timeout(15)
                ->withToken($this->getToken())
                ->get("{$this->apiUrl}/v2/categories/listProvince")
                ->throw()
                ->json();

            return $this->normalizeProvinces($res['data'] ?? []);
        });
    }

    /**
     * Trả về mảng district: ['id', 'province_id', 'code', 'name', 'status']
     */
    public function listDistricts(int $provinceId): array
    {
        return Cache::remember("vtp_districts_{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            $res = Http::timeout(15)
                ->withToken($this->getToken())
                ->get("{$this->apiUrl}/v2/categories/listDistrict", ['provinceId' => $provinceId])
                ->throw()
                ->json();

            return $this->normalizeDistricts($res['data'] ?? [], $provinceId);
        });
    }

    /**
     * Trả về mảng ward: ['id', 'district_id', 'name', 'status']
     */
    public function listWards(int $districtId): array
    {
        return Cache::remember("vtp_wards_{$districtId}", now()->addDays(7), function () use ($districtId) {
            $res = Http::timeout(15)
                ->withToken($this->getToken())
                ->get("{$this->apiUrl}/v2/categories/listWards", ['districtId' => $districtId])
                ->throw()
                ->json();

            return $this->normalizeWards($res['data'] ?? [], $districtId);
        });
    }

    /**
     * Gọi getPriceAll và trả về mảng dịch vụ đã chuẩn hoá.
     *
     * @param  int   $receiverProvinceId
     * @param  int   $receiverDistrictId
     * @param  int   $productWeight       (gam)
     * @param  int   $productPrice        (đồng, để VTP tính VAT bồi thường)
     * @param  bool  $isCod               bật thì MONEY_COLLECTION = productPrice
     * @param  int   $length              (cm)
     * @param  int   $width               (cm)
     * @param  int   $height              (cm)
     * @return array[] [{service_code, service_name, fee, vat, total_fee, kpi_ht, exchange_weight}]
     */
    public function getPriceAll(
        int $receiverProvinceId,
        int $receiverDistrictId,
        int $productWeight,
        int $productPrice,
        bool $isCod = false,
        int $length = 20,
        int $width = 15,
        int $height = 10
    ): array {
        // Trọng lượng quy đổi: L×W×H / 6000, lấy max với trọng lượng thực
        $volumetricWeight = (int) ceil($length * $width * $height / 6000);
        $chargeableWeight = max($productWeight, $volumetricWeight * 1000); // convert kg→g

        $payload = [
            'SENDER_PROVINCE'   => config('viettelpost.sender_province_id'),
            'SENDER_DISTRICT'   => config('viettelpost.sender_district_id'),
            'RECEIVER_PROVINCE' => $receiverProvinceId,
            'RECEIVER_DISTRICT' => $receiverDistrictId,
            'PRODUCT_WEIGHT'    => $chargeableWeight,
            'PRODUCT_PRICE'     => $productPrice,
            'MONEY_COLLECTION'  => $isCod ? $productPrice : 0,
            'PRODUCT_TYPE'      => config('viettelpost.default_product_type', 'HH'),
            'NATIONAL_TYPE'     => 1,
        ];

        Log::channel('shipping')->info('VTP getPriceAll request', $payload);

        $res = Http::timeout(15)
            ->withToken($this->getToken())
            ->post("{$this->apiUrl}/v2/order/getPriceAll", $payload)
            ->throw()
            ->json();

        $services = $res['data'] ?? [];

        Log::channel('shipping')->info('VTP getPriceAll response', ['count' => count($services)]);

        return collect($services)->map(fn ($s) => [
            'service_code'    => $s['MA_DV_CHINH'] ?? $s['SERVICE_CODE'] ?? '',
            'service_name'    => $s['TEN_DICHVU'] ?? $s['SERVICE_NAME'] ?? '',
            'fee'             => (int) ($s['GIA_CUOC'] ?? $s['MONEY_TOTAL'] ?? 0),
            'vat'             => (int) ($s['VAT'] ?? 0),
            'total_fee'       => (int) ($s['MONEY_TOTAL'] ?? ($s['GIA_CUOC'] ?? 0)),
            'kpi_ht'          => $s['THOI_GIAN_PHAT'] ?? $s['KPI_HT'] ?? null,
            'exchange_weight' => (int) ($s['TRONG_LUONG_QUY_DOI'] ?? $chargeableWeight),
        ])->values()->all();
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
}
