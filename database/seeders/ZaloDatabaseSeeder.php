<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ZaloDatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            ZaloCategoriesSeeder::class,
            ZaloProductsSeeder::class,
            BannersSeeder::class,
            StationsSeeder::class,
            ZaloOrdersSeeder::class,
        ]);
    }
}
