<?php

namespace Tests\Feature\Shipping;

use App\Models\Station;
use App\Services\StationPickerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit-level tests cho thuật toán chọn trạm:
 *  1) Province match → trạm cùng tỉnh
 *  2) Multi-match cùng tỉnh + có coords → Haversine
 *  3) Multi-match cùng tỉnh + KHÔNG coords → trạm đầu tiên (id nhỏ nhất)
 *  4) Không match tỉnh + có coords → Haversine trên toàn bộ trạm
 *  5) Không trạm cấu hình → null
 *  6) Trạm có nhưng chưa cấu hình VTP → null
 */
class StationPickerTest extends TestCase
{
    use RefreshDatabase;

    private StationPickerService $picker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(StationPickerService::CACHE_KEY);
        $this->picker = app(StationPickerService::class);
    }

    private function makeStation(array $attrs): Station
    {
        return Station::create(array_merge([
            'name'    => 'Test station',
            'address' => 'addr',
        ], $attrs));
    }

    public function test_picks_station_with_matching_province(): void
    {
        $this->makeStation([
            'id' => 1, 'vtp_province_id' => 10, 'vtp_district_id' => 100, 'vtp_ward_id' => 1000,
        ]);
        $match = $this->makeStation([
            'id' => 2, 'vtp_province_id' => 20, 'vtp_district_id' => 200, 'vtp_ward_id' => 2000,
        ]);

        $picked = $this->picker->pickForReceiver(20);

        $this->assertNotNull($picked);
        $this->assertSame($match->id, $picked->id);
    }

    public function test_picks_haversine_nearest_when_multiple_province_matches(): void
    {
        // Cả hai cùng tỉnh 20; trạm 2 ở Đà Lạt, trạm 3 ở TP.HCM.
        $this->makeStation([
            'id' => 2, 'vtp_province_id' => 20, 'vtp_district_id' => 200, 'vtp_ward_id' => 2000,
            'lat' => 11.940419, 'lng' => 108.458313, // Đà Lạt
        ]);
        $closer = $this->makeStation([
            'id' => 3, 'vtp_province_id' => 20, 'vtp_district_id' => 201, 'vtp_ward_id' => 2001,
            'lat' => 10.776530, 'lng' => 106.700981, // TP.HCM
        ]);

        // Receiver ở TP.HCM → trạm 3 gần hơn
        $picked = $this->picker->pickForReceiver(20, 10.78, 106.70);

        $this->assertSame($closer->id, $picked->id);
    }

    public function test_first_match_when_multiple_province_matches_and_no_coords(): void
    {
        $first = $this->makeStation([
            'id' => 5, 'vtp_province_id' => 20, 'vtp_district_id' => 200, 'vtp_ward_id' => 2000,
        ]);
        $this->makeStation([
            'id' => 8, 'vtp_province_id' => 20, 'vtp_district_id' => 201, 'vtp_ward_id' => 2001,
        ]);

        $picked = $this->picker->pickForReceiver(20);

        $this->assertSame($first->id, $picked->id);
    }

    public function test_picks_haversine_nearest_when_no_province_match(): void
    {
        // Receiver ở tỉnh 99 — không trạm nào match.
        $far = $this->makeStation([
            'id' => 1, 'vtp_province_id' => 10, 'vtp_district_id' => 100, 'vtp_ward_id' => 1000,
            'lat' => 11.940419, 'lng' => 108.458313, // Đà Lạt
        ]);
        $close = $this->makeStation([
            'id' => 2, 'vtp_province_id' => 20, 'vtp_district_id' => 200, 'vtp_ward_id' => 2000,
            'lat' => 10.776530, 'lng' => 106.700981, // TP.HCM
        ]);

        // Receiver ở Cần Thơ — TP.HCM gần hơn Đà Lạt.
        $picked = $this->picker->pickForReceiver(99, 10.045, 105.746);

        $this->assertSame($close->id, $picked->id);
    }

    public function test_returns_null_when_no_stations_at_all(): void
    {
        $picked = $this->picker->pickForReceiver(20);
        $this->assertNull($picked);
    }

    public function test_returns_null_when_stations_exist_but_no_vtp_ids(): void
    {
        $this->makeStation(['id' => 1, 'lat' => 11.94, 'lng' => 108.45]);
        $this->makeStation(['id' => 2, 'lat' => 10.77, 'lng' => 106.70]);

        $picked = $this->picker->pickForReceiver(20);

        $this->assertNull($picked);
    }
}
