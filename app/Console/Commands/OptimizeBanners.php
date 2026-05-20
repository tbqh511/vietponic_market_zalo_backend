<?php

namespace App\Console\Commands;

use App\Models\Banner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OptimizeBanners extends Command
{
    protected $signature = 'banners:optimize {--dry-run : Show what would change without writing}';
    protected $description = 'Re-encode existing banner JPEGs at q=82 and backfill intrinsic dimensions';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('Dry run — no files or DB rows will be modified.');
        }

        $totalBefore = 0;
        $totalAfter = 0;

        foreach (Banner::all() as $banner) {
            if (!$banner->image) {
                $this->warn("Skip (no image path): #{$banner->id}");
                continue;
            }

            $abs = public_path($banner->image);
            if (!file_exists($abs)) {
                $this->warn("Skip (missing on disk): {$banner->image}");
                continue;
            }

            $info = @getimagesize($abs);
            if (!$info) {
                $this->warn("Skip (not a readable image): {$banner->image}");
                continue;
            }

            [$w, $h] = $info;
            $before = filesize($abs);
            $totalBefore += $before;

            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

            if (!$dry && in_array($ext, ['jpg', 'jpeg'], true)) {
                $img = @imagecreatefromjpeg($abs);
                if ($img) {
                    imagejpeg($img, $abs, 82);
                    imagedestroy($img);
                }
            } elseif (!$dry && $ext === 'png') {
                $img = @imagecreatefrompng($abs);
                if ($img) {
                    imagejpeg($img, $abs, 82);
                    imagedestroy($img);
                }
            }

            $banner->intrinsic_width = $w;
            $banner->intrinsic_height = $h;
            $banner->intrinsic_aspect = "{$w}:{$h}";
            if (!$dry) {
                $banner->save();
            }

            if (!$dry) {
                clearstatcache(true, $abs);
            }
            $after = $dry ? $before : filesize($abs);
            $totalAfter += $after;

            $this->line(sprintf(
                "#%d %s  %dx%d  %d KB %s %d KB",
                $banner->id,
                $banner->image,
                $w,
                $h,
                (int) round($before / 1024),
                $dry ? '(dry)' : '→',
                (int) round($after / 1024)
            ));
        }

        $this->info(sprintf(
            "Total: %d KB %s %d KB (saved %d KB)",
            (int) round($totalBefore / 1024),
            $dry ? '(dry)' : '→',
            (int) round($totalAfter / 1024),
            (int) round(($totalBefore - $totalAfter) / 1024)
        ));

        if (!$dry) {
            Cache::forget('banners.list.v1');
            $this->info('Cleared banners.list.v1 cache.');
        }

        return self::SUCCESS;
    }
}
