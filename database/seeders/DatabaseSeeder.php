<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
            // Core data seeders (mock -> database)
            $this->call([
                BannersSeeder::class,
                CategoriesSeeder::class,
                ProductsSeeder::class,
                StationsSeeder::class,
                OrdersSeeder::class,
            ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
