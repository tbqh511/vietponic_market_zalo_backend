<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy existing image paths from zalo_products into zalo_product_images
        // Only for products that have an image set and no rows in the new table yet
        $products = DB::table('zalo_products')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->whereNotIn('id', function ($q) {
                $q->select('product_id')->from('zalo_product_images');
            })
            ->get(['id', 'image']);

        $rows = $products->map(fn($p) => [
            'product_id'  => $p->id,
            'image_path'  => $p->image,
            'sort_order'  => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ])->all();

        if (!empty($rows)) {
            DB::table('zalo_product_images')->insert($rows);
        }
    }

    public function down(): void
    {
        // Remove only rows that were backfilled (sort_order = 0 and path matches zalo_products.image)
        DB::statement('
            DELETE pi FROM zalo_product_images pi
            INNER JOIN zalo_products p ON p.id = pi.product_id
            WHERE pi.image_path = p.image AND pi.sort_order = 0
        ');
    }
};
