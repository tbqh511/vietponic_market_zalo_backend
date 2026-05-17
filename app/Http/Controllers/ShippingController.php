<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateShippingRequest;
use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Models\ZaloProduct;
use App\Services\StationPickerService;
use App\Services\ViettelPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    public function __construct(
        private ViettelPostService $vtp,
        private StationPickerService $picker,
    ) {
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
        }

        // Chọn trạm lấy hàng dựa trên bảng `stations` (admin cấu hình VTP IDs).
        // Ưu tiên cùng tỉnh người nhận; fallback theo Haversine nếu có toạ độ.
        $station = $this->picker->pickForReceiver(
            $request->integer('receiver_province_id'),
            $request->filled('receiver_lat') ? (float) $request->input('receiver_lat') : null,
            $request->filled('receiver_lng') ? (float) $request->input('receiver_lng') : null,
        );

        if (!$station) {
            Log::channel('shipping')->error('[NO_PICKUP_STATION] Không có trạm nào cấu hình VTP', [
                'receiver_province' => $request->integer('receiver_province_id'),
            ]);
            return response()->json([
                'error'   => true,
                'code'    => 'NO_PICKUP_STATION',
                'message' => 'Hiện chưa có trạm lấy hàng phù hợp, vui lòng liên hệ shop.',
            ], 422);
        }

        $senderProvinceId = (int) $station->vtp_province_id;
        $senderDistrictId = (int) ($station->vtp_district_id ?? 0);
        $senderWardId     = (int) ($station->vtp_ward_id ?? 0);

        // VTP /v2/order/getPriceAll: lấy danh sách dịch vụ khả dụng cho tuyến gửi/nhận.
        // Dùng `TYPE` (không phải `NATIONAL_TYPE` — đó là field của getPrice singular).
        // V3 đã bỏ district → set = 0 (đã verify trên prod: được chấp nhận).
        $payload = [
            'PRODUCT_WEIGHT'    => $totalWeight,
            'PRODUCT_PRICE'     => $request->integer('product_price'),
            'MONEY_COLLECTION'  => $isCod ? $request->integer('product_price') : 0,
            'SENDER_PROVINCE'   => $senderProvinceId,
            'SENDER_DISTRICT'   => $senderDistrictId,
            'SENDER_WARD'       => $senderWardId,
            'RECEIVER_PROVINCE' => $request->integer('receiver_province_id'),
            'RECEIVER_DISTRICT' => $districtId,
            'RECEIVER_WARD'     => $request->integer('receiver_ward_id'),
            'PRODUCT_TYPE'      => config('viettelpost.default_product_type', 'HH'),
            'TYPE'              => 1,
            'PRODUCT_LENGTH'    => $maxLength ?: 20,
            'PRODUCT_WIDTH'     => $maxWidth  ?: 15,
            'PRODUCT_HEIGHT'    => $maxHeight ?: 10,
        ];

        $logContext = [
            'picked_station_id' => $station->id,
            'sender_province'   => $senderProvinceId,
            'sender_district'   => $senderDistrictId,
            'sender_ward'       => $senderWardId,
            'receiver_province' => $request->integer('receiver_province_id'),
            'receiver_district' => $districtId,
            'receiver_ward'     => $request->integer('receiver_ward_id'),
            'weight'            => $totalWeight,
        ];

        try {
            $vtpServices = $this->vtp->getPriceAll($payload);
            $services    = array_values(array_filter(array_map(
                fn ($s) => $this->mapVtpService($s),
                $vtpServices,
            )));

            if (empty($services)) {
                Log::channel('shipping')->warning('[FALLBACK] VTP getPriceAll trả về rỗng', $logContext);
                $response = response()->json(['error' => false, 'data' => $this->fallbackServices($isCod), 'fallback' => true]);
            } else {
                $response = response()->json(['error' => false, 'data' => $services]);
            }

            if (config('app.debug')) {
                $response->header('X-Picked-Station-Id', (string) $station->id);
            }
            return $response;

        } catch (\Illuminate\Http\Client\RequestException | \Illuminate\Http\Client\ConnectionException $e) {
            Log::channel('shipping')->warning('[FALLBACK] VTP API không phản hồi', $logContext + ['error' => $e->getMessage()]);

            return response()->json(['error' => false, 'data' => $this->fallbackServices($isCod), 'fallback' => true]);

        } catch (\Throwable $e) {
            Log::channel('shipping')->error('[FALLBACK] estimate: lỗi không xác định', $logContext + ['error' => $e->getMessage()]);
            return response()->json(['error' => true, 'message' => 'Không thể tính phí vận chuyển, vui lòng thử lại'], 500);
        }
    }

    /**
     * Map item từ VTP getPriceAll → shape mà frontend `ShippingService` đợi (lowercase).
     * VTP trả: {MA_DV_CHINH, TEN_DICHVU, GIA_CUOC, THOI_GIAN, EXCHANGE_WEIGHT, EXTRA_SERVICE}
     * Lưu ý: getPriceAll KHÔNG có breakdown VAT → `vat = 0`, `fee = total_fee`.
     */
    private function mapVtpService(array $svc): ?array
    {
        $code  = $svc['MA_DV_CHINH'] ?? null;
        $total = (int) ($svc['GIA_CUOC'] ?? 0);
        if (!$code || $total <= 0) {
            return null;
        }

        return [
            'service_code'    => $code,
            'service_name'    => $svc['TEN_DICHVU'] ?? $code,
            'fee'             => $total,
            'vat'             => 0,
            'total_fee'       => $total,
            'kpi_ht'          => $svc['THOI_GIAN'] ?? null,
            'exchange_weight' => isset($svc['EXCHANGE_WEIGHT']) ? (int) $svc['EXCHANGE_WEIGHT'] : null,
        ];
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
