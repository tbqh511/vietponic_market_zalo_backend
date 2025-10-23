<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/categories.json');
        if (! file_exists($path)) return;

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) return;

        DB::table('categories')->truncate();

        foreach ($data as $item) {
            DB::table('categories')->insert([
                'id' => $item['id'] ?? null,
                'name' => $item['name'] ?? null,
                'image' => $item['image'] ?? null,
                'slug' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
