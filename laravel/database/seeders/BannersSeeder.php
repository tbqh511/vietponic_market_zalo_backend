<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Banner;

class BannersSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/banners.json');
        if (!File::exists($path)) {
            $this->command->warn('mock/banners.json not found');
            return;
        }
        $json = json_decode(File::get($path), true);
        foreach ($json as $image) {
            Banner::create(['image' => $image]);
        }
    }
}
