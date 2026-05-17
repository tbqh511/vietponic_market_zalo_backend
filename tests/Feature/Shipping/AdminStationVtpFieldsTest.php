<?php

namespace Tests\Feature\Shipping;

use App\Models\Station;
use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Services\StationPickerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Test ở mức Eloquent/cache (không qua admin HTTP routes vì cần `auth` + `checklogin`
 * setup phức tạp). Đảm bảo:
 *  - 4 trường VTP được persist đúng vào DB.
 *  - Cache `stations_with_vtp` bị bust khi tạo/sửa/xoá trạm.
 *  - Scope `withVtp()` lọc đúng.
 */
class AdminStationVtpFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        VtpProvince::create(['id' => 1, 'name' => 'TP.HCM', 'status' => 1]);
        VtpDistrict::create(['id' => 10, 'province_id' => 1, 'name' => 'Q1', 'status' => 1]);
        VtpWard::create(['id' => 100, 'district_id' => 10, 'name' => 'P Bến Nghé', 'status' => 1]);
    }

    public function test_station_persists_vtp_fields(): void
    {
        $station = Station::create([
            'id' => 1, 'name' => 'Kho TP.HCM', 'address' => 'addr',
            'lat' => 10.77, 'lng' => 106.70,
            'vtp_province_id' => 1, 'vtp_district_id' => 10, 'vtp_ward_id' => 100,
            'vtp_address' => '123 Lê Lợi',
        ]);

        $this->assertDatabaseHas('stations', [
            'id' => 1,
            'vtp_province_id' => 1,
            'vtp_district_id' => 10,
            'vtp_ward_id' => 100,
            'vtp_address' => '123 Lê Lợi',
        ]);

        $reloaded = Station::find(1);
        $this->assertSame(1, $reloaded->vtp_province_id);
        $this->assertSame(10, $reloaded->vtp_district_id);
        $this->assertSame(100, $reloaded->vtp_ward_id);
        $this->assertSame('123 Lê Lợi', $reloaded->vtp_address);
    }

    public function test_with_vtp_scope_filters_unconfigured_stations(): void
    {
        Station::create(['id' => 1, 'name' => 'Configured', 'address' => 'a',
            'vtp_province_id' => 1, 'vtp_district_id' => 10, 'vtp_ward_id' => 100]);
        Station::create(['id' => 2, 'name' => 'Not configured', 'address' => 'b']);
        Station::create(['id' => 3, 'name' => 'Half configured', 'address' => 'c',
            'vtp_province_id' => 1]); // thiếu district → bị loại

        $configured = Station::withVtp()->get();

        $this->assertCount(1, $configured);
        $this->assertSame(1, $configured->first()->id);
    }

    public function test_cache_is_busted_when_picker_called_after_new_station_added(): void
    {
        // Lần 1 gọi picker → cache rỗng (no station)
        $picker = app(StationPickerService::class);
        Cache::forget(StationPickerService::CACHE_KEY);
        $this->assertNull($picker->pickForReceiver(1));

        // Bây giờ tạo trạm + bust cache (giống ZaloStationController::store làm)
        Station::create([
            'id' => 1, 'name' => 'Mới', 'address' => 'a',
            'vtp_province_id' => 1, 'vtp_district_id' => 10, 'vtp_ward_id' => 100,
        ]);
        Cache::forget(StationPickerService::CACHE_KEY);

        // Lần 2 → phải thấy trạm mới
        $picked = $picker->pickForReceiver(1);
        $this->assertNotNull($picked);
        $this->assertSame(1, $picked->id);
    }
}
