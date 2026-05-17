<?php

namespace App\Services;

use App\Models\Station;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StationPickerService
{
    public const CACHE_KEY = 'stations_with_vtp';

    /**
     * Chọn trạm lấy hàng phù hợp nhất cho người nhận.
     *
     * Thuật toán:
     *  1. Trạm có cùng vtp_province_id với người nhận → ưu tiên. Nếu nhiều: chọn gần lat/lng nhất
     *     (nếu có toạ độ người nhận) hoặc trạm đầu tiên theo id.
     *  2. Không match tỉnh: nếu có toạ độ người nhận và có trạm có lat/lng → chọn theo Haversine.
     *  3. Cuối cùng: trạm đầu tiên đã cấu hình VTP (degraded — log warning ở caller).
     *  4. Không có trạm nào cấu hình VTP → null (caller trả 422).
     */
    public function pickForReceiver(
        int $receiverProvinceId,
        ?float $receiverLat = null,
        ?float $receiverLng = null
    ): ?Station {
        $configured = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            fn() => Station::withVtp()->orderBy('id')->get()
        );

        if ($configured->isEmpty()) {
            return null;
        }

        $matches = $configured->where('vtp_province_id', $receiverProvinceId)->values();
        if ($matches->isNotEmpty()) {
            if ($matches->count() === 1) {
                return $matches->first();
            }
            if ($receiverLat !== null && $receiverLng !== null) {
                return $this->nearest($matches, $receiverLat, $receiverLng) ?? $matches->first();
            }
            return $matches->first();
        }

        if ($receiverLat !== null && $receiverLng !== null) {
            $withCoords = $configured->filter(
                fn($s) => $s->lat !== null && $s->lng !== null
            );
            if ($withCoords->isNotEmpty()) {
                return $this->nearest($withCoords, $receiverLat, $receiverLng);
            }
        }

        return $configured->first();
    }

    private function nearest(Collection $stations, float $lat, float $lng): ?Station
    {
        return $stations
            ->filter(fn($s) => $s->lat !== null && $s->lng !== null)
            ->sortBy(fn($s) => $this->haversine($lat, $lng, (float) $s->lat, (float) $s->lng))
            ->first();
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0; // Bán kính Trái Đất theo km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $R * asin(min(1.0, sqrt($a)));
    }
}
