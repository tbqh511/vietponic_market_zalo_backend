<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\ClearRedisCache;
use App\Console\Commands\SeedProductDimensions;
use App\Console\Commands\SyncVtpLocations;
use App\Console\Commands\VtpRefreshToken;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        ClearRedisCache::class,
        SeedProductDimensions::class,
        SyncVtpLocations::class,
        VtpRefreshToken::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Refresh danh mục VTP mỗi tuần vào 2 giờ sáng thứ Hai
        $schedule->command('vtp:sync-locations --no-wards')
            ->weeklyOn(1, '02:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('vtp:refresh-token')->weekly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
