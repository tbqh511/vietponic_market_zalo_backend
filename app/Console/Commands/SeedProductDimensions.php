<?php

namespace App\Console\Commands;

use App\Models\ZaloProduct;
use Illuminate\Console\Command;

/**
 * Áp default trọng lượng/kích thước cho sản phẩm chưa có dimension được set thủ công.
 * Quy tắc theo category — admin có thể override sau qua UI.
 */
class SeedProductDimensions extends Command
{
    protected $signature = 'vietponics:seed-product-dimensions {--force : Ghi đè cả những SP đã có giá trị tuỳ chỉnh}';

    protected $description = 'Áp trọng lượng & kích thước mặc định theo category cho ZaloProduct';

    /**
     * category_id => [weight (g), length, width, height (cm)]
     * Categories tham chiếu seeders/vietponics/vietponics_seed_data.md.
     */
    private array $defaults = [
        1  => [300, 25, 18, 8],   // Rau lá & rau thuỷ canh
        2  => [700, 25, 20, 12],  // Trái cây ôn đới
        3  => [500, 30, 25, 25],  // Hoa tươi & hoa chậu (cồng kềnh)
        4  => [500, 22, 16, 10],  // Cà phê & chè
        5  => [700, 25, 20, 12],  // Rau củ quả ôn đới
        6  => [400, 22, 16, 10],  // Nấm & rau đặc sản
        7  => [800, 28, 22, 15],  // Trái cây nhiệt đới
        8  => [300, 20, 15, 8],   // Dược liệu & thảo mộc
        9  => [1000, 25, 18, 12], // Hạt & ngũ cốc
        10 => [1000, 25, 18, 15], // Nông sản chế biến
    ];

    private array $fallback = [500, 20, 15, 10];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $updated = 0;
        $skipped = 0;

        ZaloProduct::query()->orderBy('id')->chunkById(200, function ($products) use ($force, &$updated, &$skipped) {
            foreach ($products as $product) {
                [$w, $l, $wd, $h] = $this->defaults[$product->category_id] ?? $this->fallback;

                // Nếu admin đã set giá trị khác default 500/20/15/10 → giữ nguyên (trừ khi --force)
                $alreadyCustomised = (
                    (int) $product->weight !== 500
                    || (int) $product->length !== 20
                    || (int) $product->width !== 15
                    || (int) $product->height !== 10
                );

                if ($alreadyCustomised && !$force) {
                    $skipped++;
                    continue;
                }

                $product->update([
                    'weight' => $w,
                    'length' => $l,
                    'width'  => $wd,
                    'height' => $h,
                ]);
                $updated++;
            }
        });

        $this->info("Updated: {$updated}");
        $this->line("Skipped (đã có dimension tuỳ chỉnh): {$skipped}");

        return self::SUCCESS;
    }
}
