<?php

namespace App\Console\Commands;

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
                            {--province= : Chỉ sync wards của 1 tỉnh (province_id)}
                            {--no-wards  : Bỏ qua sync wards (nhanh hơn, dùng khi chỉ cần tỉnh/thành)}';

    protected $description = 'Kéo toàn bộ danh mục tỉnh/huyện/xã của ViettelPost về DB';

    public function __construct(private ViettelPostService $vtp)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Bắt đầu sync danh mục địa lý từ VTP v3...');

        try {
            $this->syncProvinces();

            $onlyProvinceIds = $this->option('province') ? [(int) $this->option('province')] : null;

            if (!$this->option('no-wards')) {
                $this->syncWards($onlyProvinceIds);
            }

            Cache::forget('vtp_provinces');
            Cache::forget('vtp_provinces_v3');
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
        $this->info('Sync tỉnh/thành (v3)...');
        $provinces = $this->vtp->listProvincesV3();
        $now = Carbon::now();

        foreach ($provinces as $p) {
            VtpProvince::updateOrCreate(
                ['id' => $p['id']],
                ['code' => $p['code'], 'name' => $p['name'], 'status' => $p['status'], 'synced_at' => $now]
            );
        }

        $this->line("  → " . count($provinces) . " tỉnh/thành");
    }

    private function syncWards(?array $onlyProvinceIds): void
    {
        $this->info('Sync phường/xã theo tỉnh (v3, có thể mất vài phút)...');
        $query = VtpProvince::where('status', 1);
        if ($onlyProvinceIds) {
            $query->whereIn('id', $onlyProvinceIds);
        }
        $provinces = $query->get();
        $now = Carbon::now();
        $total = 0;
        $bar = $this->output->createProgressBar($provinces->count());

        foreach ($provinces as $province) {
            try {
                $wards = $this->vtp->listWardsV3($province->id);
                foreach ($wards as $w) {
                    VtpWard::updateOrCreate(
                        ['id' => $w['id']],
                        [
                            'province_id' => $province->id,
                            'district_id' => $w['district_id'] ?: null,
                            'name'        => $w['name'],
                            'status'      => $w['status'],
                            'synced_at'   => $now,
                        ]
                    );
                }
                Cache::forget("vtp_wards_v3_{$province->id}");
                $total += count($wards);
            } catch (\Throwable $e) {
                $this->warn("  ✗ Bỏ qua tỉnh {$province->id} ({$province->name}): {$e->getMessage()}");
                Log::channel('shipping')->warning("syncWards skip province {$province->id}", ['error' => $e->getMessage()]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("  → {$total} phường/xã");
    }
}
