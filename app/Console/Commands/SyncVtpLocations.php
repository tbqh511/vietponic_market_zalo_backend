<?php

namespace App\Console\Commands;

use App\Models\VtpDistrict;
use App\Models\VtpProvince;
use App\Models\VtpWard;
use App\Services\ViettelPostService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncVtpLocations extends Command
{
    protected $signature = 'vtp:sync-locations
                            {--province= : Chỉ sync districts/wards của 1 tỉnh (province_id)}
                            {--no-wards  : Bỏ qua sync wards (nhanh hơn, dùng khi chỉ cần tỉnh/huyện)}';

    protected $description = 'Kéo toàn bộ danh mục tỉnh/huyện/xã của ViettelPost về DB';

    public function __construct(private ViettelPostService $vtp)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Bắt đầu sync danh mục địa lý từ VTP...');

        try {
            $this->syncProvinces();

            $onlyProvince = $this->option('province') ? [(int) $this->option('province')] : null;
            $this->syncDistricts($onlyProvince);

            if (!$this->option('no-wards')) {
                $this->syncWards($onlyProvince);
            }

            // Invalidate cache để next request lấy dữ liệu mới từ DB
            Cache::forget('vtp_provinces');
            $this->info('Xong! Cache đã được xoá.');
        } catch (\Throwable $e) {
            $this->error('Sync thất bại: ' . $e->getMessage());
            Log::channel('shipping')->error('vtp:sync-locations failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function syncProvinces(): void
    {
        $this->info('Sync tỉnh/thành...');
        $provinces = $this->vtp->listProvinces();
        $now = Carbon::now();

        foreach ($provinces as $p) {
            VtpProvince::updateOrCreate(
                ['id' => $p['id']],
                ['code' => $p['code'], 'name' => $p['name'], 'status' => $p['status'], 'synced_at' => $now]
            );
        }

        $this->line("  → " . count($provinces) . " tỉnh/thành");
    }

    private function syncDistricts(?array $onlyProvinceIds): void
    {
        $this->info('Sync quận/huyện...');
        $query = VtpProvince::where('status', 1);
        if ($onlyProvinceIds) {
            $query->whereIn('id', $onlyProvinceIds);
        }
        $provinces = $query->get();
        $now = Carbon::now();
        $total = 0;

        foreach ($provinces as $province) {
            try {
                $districts = $this->vtp->listDistricts($province->id);
                foreach ($districts as $d) {
                    VtpDistrict::updateOrCreate(
                        ['id' => $d['id']],
                        [
                            'province_id' => $province->id,
                            'code'        => $d['code'],
                            'name'        => $d['name'],
                            'status'      => $d['status'],
                            'synced_at'   => $now,
                        ]
                    );
                }
                // Xoá cache district level để route /api/locations/districts trả fresh
                Cache::forget("vtp_districts_{$province->id}");
                $total += count($districts);
            } catch (\Throwable $e) {
                $this->warn("  ✗ Bỏ qua tỉnh {$province->id} ({$province->name}): {$e->getMessage()}");
                Log::channel('shipping')->warning("syncDistricts skip province {$province->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->line("  → {$total} quận/huyện");
    }

    private function syncWards(?array $onlyProvinceIds): void
    {
        $this->info('Sync phường/xã (có thể mất vài phút)...');
        $query = VtpDistrict::where('status', 1);
        if ($onlyProvinceIds) {
            $query->whereIn('province_id', $onlyProvinceIds);
        }
        $districts = $query->get();
        $now = Carbon::now();
        $total = 0;
        $bar = $this->output->createProgressBar($districts->count());

        foreach ($districts as $district) {
            try {
                $wards = $this->vtp->listWards($district->id);
                foreach ($wards as $w) {
                    VtpWard::updateOrCreate(
                        ['id' => $w['id']],
                        [
                            'district_id' => $district->id,
                            'name'        => $w['name'],
                            'status'      => $w['status'],
                            'synced_at'   => $now,
                        ]
                    );
                }
                Cache::forget("vtp_wards_{$district->id}");
                $total += count($wards);
            } catch (\Throwable $e) {
                Log::channel('shipping')->warning("syncWards skip district {$district->id}", ['error' => $e->getMessage()]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  → {$total} phường/xã");
    }
}
