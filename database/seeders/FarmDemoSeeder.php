<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloProduct;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FarmDemoSeeder — tạo 3 farm demo + gắn sản phẩm + 1-2 batch active mỗi farm.
 *
 * Idempotent: dùng updateOrCreate cho farm (code unique) và detach-then-attach
 * cho pivot farm_product. Batch là append-only — chỉ tạo thêm nếu farm chưa
 * có batch active nào của product đó (tránh seed trùng khi chạy lại).
 *
 * Chạy:
 *   php artisan db:seed --class=FarmDemoSeeder
 *
 * Lưu ý: seeder KHÔNG tạo Customer chủ farm — admin nên gán owner thủ công
 * qua trang admin sau khi seed. owner_customer_id để null.
 */
class FarmDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy 5-7 sản phẩm rau bất kỳ làm danh mục demo. Ưu tiên sản phẩm
        // đầu tiên theo id để output deterministic giữa các lần seed.
        $products = ZaloProduct::orderBy('id')->take(7)->get();
        if ($products->isEmpty()) {
            $this->command->warn('Chưa có ZaloProduct nào — chạy ZaloProductsSeeder trước.');
            return;
        }

        $this->command->info("Sẽ gắn {$products->count()} sản phẩm cho mỗi farm.");

        $farms = [
            [
                'code'            => 'JOILEY',
                'name'            => 'Joiley Farm',
                'description'     => 'Farm thủy canh quy mô lớn tại Lâm Đồng — chuyên rau xà lách & cải.',
                'address'         => 'Đà Lạt, Lâm Đồng',
                'lat'             => 11.9404,
                'lng'             => 108.4583,
                'commission_rate' => 0.85,
                'payment_cycle'   => 'weekly',
            ],
            [
                'code'            => 'VP_HOME',
                'name'            => 'Vietponics Home Farm',
                'description'     => 'Farm nội bộ của Vietponics — dùng làm pilot R&D + bù tồn kho.',
                'address'         => 'Quận 12, TP.HCM',
                'lat'             => 10.8651,
                'lng'             => 106.6429,
                'commission_rate' => 1.00,
                'payment_cycle'   => 'monthly',
            ],
            [
                'code'            => 'DALAT_FRESH',
                'name'            => 'Đà Lạt Fresh',
                'description'     => 'Hợp tác xã rau hữu cơ Đà Lạt — nguồn cung rau ăn lá quanh năm.',
                'address'         => 'Đơn Dương, Lâm Đồng',
                'lat'             => 11.8333,
                'lng'             => 108.6500,
                'commission_rate' => 0.82,
                'payment_cycle'   => 'biweekly',
            ],
        ];

        DB::transaction(function () use ($farms, $products) {
            foreach ($farms as $farmData) {
                $farm = Farm::updateOrCreate(
                    ['code' => $farmData['code']],
                    array_merge($farmData, [
                        // Demo farm tự duyệt sẵn để dashboard chạy được ngay.
                        'is_active'   => true,
                        'approved_at' => now()->subDays(30),
                    ])
                );

                // Gắn sản phẩm qua pivot farm_product. cost_price ngẫu nhiên
                // = 50-70% giá bán để snapshot có doanh thu khác nhau giữa farm.
                $costRatio = match ($farm->code) {
                    'JOILEY'      => 0.55,
                    'VP_HOME'     => 0.65,
                    'DALAT_FRESH' => 0.50,
                    default       => 0.60,
                };

                $pivotData = [];
                foreach ($products as $idx => $product) {
                    $pivotData[$product->id] = [
                        'cost_price' => round((float) $product->price * $costRatio, 2),
                        // Mỗi farm có 1 sản phẩm "chính" — luân phiên theo id.
                        'is_primary' => ($idx % $products->count()) === 0,
                    ];
                }
                $farm->products()->sync($pivotData);

                // Tạo 1-2 batch active cho mỗi product nếu chưa có. Batch đầu
                // expire 7 ngày, batch thứ 2 expire 14 ngày → FEFO test được.
                foreach ($products as $product) {
                    $existing = FarmStockBatch::where('farm_id', $farm->id)
                        ->where('product_id', $product->id)
                        ->where('status', 'active')
                        ->count();
                    if ($existing > 0) {
                        continue;
                    }

                    $costPrice = (float) $pivotData[$product->id]['cost_price'];

                    // Batch 1: 50kg, expire 7 ngày, nhập hôm qua.
                    FarmStockBatch::create([
                        'farm_id'       => $farm->id,
                        'product_id'    => $product->id,
                        'batch_date'    => Carbon::today()->subDay()->toDateString(),
                        'quantity_in'   => 50,
                        'quantity_sold' => 0,
                        'cost_price'    => $costPrice,
                        'expire_date'   => Carbon::today()->addDays(7)->toDateString(),
                        'status'        => 'active',
                        'note'          => '[seed] Demo batch 1',
                    ]);

                    // Batch 2 (chỉ cho farm JOILEY & DALAT_FRESH — không cho
                    // VP_HOME để có biến thể single-batch trong dataset).
                    if (in_array($farm->code, ['JOILEY', 'DALAT_FRESH'], true)) {
                        FarmStockBatch::create([
                            'farm_id'       => $farm->id,
                            'product_id'    => $product->id,
                            'batch_date'    => Carbon::today()->toDateString(),
                            'quantity_in'   => 30,
                            'quantity_sold' => 0,
                            'cost_price'    => $costPrice,
                            'expire_date'   => Carbon::today()->addDays(14)->toDateString(),
                            'status'        => 'active',
                            'note'          => '[seed] Demo batch 2 (fresher)',
                        ]);
                    }
                }

                $this->command->info("✓ {$farm->name} ({$farm->code}) — {$products->count()} sản phẩm");
            }
        });

        $this->command->info('Xong! Chạy `php artisan farms:snapshot-daily` để tạo accrued payouts cho farms.');
    }
}
