<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\ZaloCategory;

class ZaloCategoriesSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/categories.json');
        if (!File::exists($path)) {
            $this->command->warn('mock/categories.json not found');
            return;
        }
        $json = json_decode(File::get($path), true);
        foreach ($json as $item) {
            ZaloCategory::updateOrCreate(['id' => $item['id']], [
                'name' => $item['name'] ?? null,
                'image' => $item['image'] ?? null,
            ]);
        }
    }
}
