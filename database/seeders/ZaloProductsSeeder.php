<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\ZaloProduct;

class ZaloProductsSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/products.json');
        if (!File::exists($path)) {
            $this->command->warn('mock/products.json not found');
            return;
        }
        $json = json_decode(File::get($path), true);
        foreach ($json as $item) {
            ZaloProduct::updateOrCreate(['id' => $item['id']], [
                'category_id' => $item['categoryId'] ?? null,
                'name' => $item['name'] ?? null,
                'price' => $item['price'] ?? 0,
                'original_price' => $item['originalPrice'] ?? null,
                'image' => $item['image'] ?? null,
                'detail' => $item['detail'] ?? null,
            ]);
        }
    }
}
