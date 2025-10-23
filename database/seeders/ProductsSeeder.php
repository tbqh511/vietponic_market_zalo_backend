<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/products.json');
        if (! file_exists($path)) return;

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) return;

        DB::table('products')->truncate();

        foreach ($data as $item) {
            DB::table('products')->insert([
                'id' => $item['id'] ?? null,
                'category_id' => $item['category_id'] ?? null,
                'name' => $item['name'] ?? null,
                'price' => isset($item['price']) ? intval($item['price']) : 0,
                'original_price' => isset($item['original_price']) ? intval($item['original_price']) : null,
                'image' => $item['image'] ?? null,
                'detail' => $item['detail'] ?? null,
                'sku' => $item['sku'] ?? null,
                'stock' => isset($item['stock']) ? intval($item['stock']) : 0,
                'is_active' => isset($item['is_active']) ? (bool)$item['is_active'] : true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
