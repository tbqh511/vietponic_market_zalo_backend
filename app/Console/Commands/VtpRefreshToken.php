<?php

namespace App\Console\Commands;

use App\Services\ViettelPostService;
use Illuminate\Console\Command;

class VtpRefreshToken extends Command
{
    protected $signature = 'vtp:refresh-token {--force : Bỏ qua kiểm tra TTL, refresh ngay lập tức}';

    protected $description = 'Kiểm tra và làm mới token dài hạn Viettel Post nếu cần';

    public function handle(ViettelPostService $vtp): int
    {
        if ($this->option('force')) {
            $vtp->forceRefreshToken();
            $this->info('VTP token force-refreshed successfully.');

            return self::SUCCESS;
        }

        $days = $vtp->getDaysUntilTokenExpiry();

        if ($days === null) {
            $this->info('No VTP token cached. Fetching now...');
            $vtp->getToken();
            $this->info('VTP token fetched and cached.');

            return self::SUCCESS;
        }

        $warnThreshold = config('viettelpost.token_warn_days', 30);

        if ($days < $warnThreshold) {
            $vtp->forceRefreshToken();
            $this->info("Token refreshed. {$days} days were remaining.");
        } else {
            $this->info("Token OK. {$days} days remaining. No refresh needed.");
        }

        return self::SUCCESS;
    }
}
