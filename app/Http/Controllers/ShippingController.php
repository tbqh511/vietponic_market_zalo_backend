<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateShippingRequest;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Models\ZaloProduct;
use App\Services\ViettelPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    public function __construct(private ViettelPostService $vtp)
    {
    }

    // ── Location endpoints (public) ──────────────────────────────────────────

    public function provinces(): JsonResponse
    {
        $data = Cache::remember('api_vtp_provinces', now()->addDays(30), function () {
            return VtpProvince::where('status', 1)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->toArray();
        });

        return response()->json(['error' => false, 'data' => $data]);
    }

    public function districts(Request $request): JsonResponse
    {
        $provinceId = (int) $request->query('province_id');
        if (!$provinceId) {
            return response()->json(['error' => true, 'message' => 'province_id bắt buộc'], 422);
        }

        $data = Cache::remember("api_vtp_districts_{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            return VtpDistrict::where('province_id', $provinceId)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->toArray();
        });

        return response()->json(['error' => false, 'data' => $data]);
    }

    public function wards(Request $request): JsonResponse
    {
        $provinceId = (int) $request->query('province_id');
        if (!$provinceId) {
            return response()->json(['error' => true, 'message' => 'province_id bắt buộc'], 422);
        }

        $data = Cache::remember("api_vtp_wards_province_{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            return VtpWard::where('province_id', $provinceId)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'district_id', 'name'])
                ->toArray();
        });

        return response()->json(['error' => false, 'data' => $data]);
    }

    // ── Shipping estimate (JWT protected) ────────────────────────────────────

    public function estimate(EstimateShippingRequest $request): JsonResponse
    {
        $items    = $request->input('items');
        $isCod    = (bool) $request->input('is_cod', false);

        // Tính tổng weight & kích thước gộp từ DB (không tin payload client)
        $productIds = collect($items)->pluck('product_id')->unique()->toArray();
        $products   = ZaloProduct::whereIn('id', $productIds)->get()->keyBy('id');

        $totalWeight = 0;
        $maxLength   = 0;
        $maxWidth    = 0;
        $maxHeight   = 0;

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                continue;
            }
            $qty          = (int) $item['quantity'];
            $totalWeight += $product->weight * $qty;
            $maxLength    = max($maxLength, $product->length);
            $maxWidth     = max($maxWidth, $product->width);
            $maxHeight    = max($maxHeight, $product->height);
        }

        $totalWeight = max($totalWeight, 1);

        // Nếu client không gửi district_id (v3 API bỏ cấp huyện), tự lookup từ ward
        $districtId = $request->integer('receiver_district_id');
        if (!$districtId && $request->integer('receiver_ward_id')) {
            $ward = VtpWard::find($request->integer('receiver_ward_id'));
            $districtId = $ward?->district_id ?? 0;
            Log::channel('shipping')->info('estimate: district_id resolved from ward', [
                'ward_id'     => $request->integer('receiver_ward_id'),
                'district_id' => $districtId,
            ]);
        }

        Log::channel('shipping')->info('estimate: request params', [
            'province_id' => $request->integer('receiver_province_id'),
            'district_id' => $districtId,
            'ward_id'     => $request->integer('receiver_ward_id'),
            'weight'      => $totalWeight,
            'price'       => $request->integer('product_price'),
        ]);

        try {
            $services = $this->vtp->getPriceAll([
                'PRODUCT_WEIGHT'      => $totalWeight,
                'PRODUCT_PRICE'       => $request->integer('product_price'),
                'MONEY_COLLECTION'    => $isCod ? $request->integer('product_price') : 0,
                'RECEIVER_PROVINCE'   => $request->integer('receiver_province_id'),
                'RECEIVER_DISTRICT'   => $districtId,
                'PRODUCT_TYPE'        => 'HH',
                'NATIONAL_TYPE'       => 1,
                'LENGTH'              => $maxLength ?: 20,
                'WIDTH'               => $maxWidth  ?: 15,
                'HEIGHT'              => $maxHeight ?: 10,
            ]);

            if (empty($services)) {
                Log::channel('shipping')->warning('VTP getPriceAll trả về rỗng', [
                    'province'  => $request->integer('receiver_province_id'),
                    'district'  => $request->integer('receiver_district_id'),
                    'weight'    => $totalWeight,
                ]);
                return response()->json(['error' => false, 'data' => $this->fallbackServices($isCod)]);
            }

            return response()->json(['error' => false, 'data' => $services]);

        } catch (\Illuminate\Http\Client\RequestException | \Illuminate\Http\Client\ConnectionException $e) {
            Log::channel('shipping')->warning('VTP API không phản hồi — dùng bảng phí phẳng', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => false, 'data' => $this->fallbackServices($isCod), 'fallback' => true]);

        } catch (\Throwable $e) {
            Log::channel('shipping')->error('estimate: lỗi không xác định', ['error' => $e->getMessage()]);
            return response()->json(['error' => true, 'message' => 'Không thể tính phí vận chuyển, vui lòng thử lại'], 500);
        }
    }

    // ── Flat-fee fallback (Phase 6) ──────────────────────────────────────────

    /**
     * Bảng phí phẳng theo zone khi VTP API down.
     * Trả về 3 "dịch vụ" tương ứng với nội tỉnh / liên tỉnh gần / liên tỉnh xa.
     */
    private function fallbackServices(bool $isCod): array
    {
        $fees = config('viettelpost.fallback_fees');

        $services = [
            [
                'service_code'    => 'FLAT_INTRA',
                'service_name'    => 'Phí ước tính — Nội tỉnh (offline)',
                'fee'             => $fees['intra_province'],
                'vat'             => 0,
                'total_fee'       => $fees['intra_province'],
                'kpi_ht'          => '1 ngày',
                'exchange_weight' => null,
            ],
            [
                'service_code'    => 'FLAT_SHORT',
                'service_name'    => 'Phí ước tính — Liên tỉnh ≤300km (offline)',
                'fee'             => $fees['short_haul'],
                'vat'             => 0,
                'total_fee'       => $fees['short_haul'],
                'kpi_ht'          => '2-3 ngày',
                'exchange_weight' => null,
            ],
            [
                'service_code'    => 'FLAT_LONG',
                'service_name'    => 'Phí ước tính — Liên tỉnh >300km (offline)',
                'fee'             => $fees['long_haul'],
                'vat'             => 0,
                'total_fee'       => $fees['long_haul'],
                'kpi_ht'          => '3-5 ngày',
                'exchange_weight' => null,
            ],
        ];

        if ($isCod) {
            // COD thêm phí thu hộ ước tính 1%
            foreach ($services as &$s) {
                $codFee        = (int) ceil($s['total_fee'] * 0.01);
                $s['total_fee'] += $codFee;
                $s['service_name'] .= ' (COD)';
            }
        }

        return $services;
    }
}
