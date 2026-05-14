<?php

namespace Database\Seeders;

use App\Models\ZaloProduct;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Seed initial stock levels for all existing products.
     * Safe to run multiple times — only updates products where stock is still 0.
     */
    public function run(): void
    {
        ZaloProduct::where('stock', 0)->each(function (ZaloProduct $product) {
            $product->update([
                'stock'          => 100,
                'stock_reserved' => 0,
                'reorder_point'  => 10,
            ]);
        });

        $this->command->info('StockSeeder: initialized stock=100, reorder_point=10 for all products with stock=0.');
    }
}
