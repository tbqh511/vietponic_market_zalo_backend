<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BannersSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/banners.json');
        if (! file_exists($path)) return;

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) return;

        DB::table('banners')->truncate();

        foreach ($data as $i => $imageUrl) {
            DB::table('banners')->insert([
                'image_url' => $imageUrl,
                'position' => $i + 1,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
