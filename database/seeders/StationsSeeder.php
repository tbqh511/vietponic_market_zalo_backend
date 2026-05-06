<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Station;

class StationsSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/stations.json');
        if (!File::exists($path)) {
            $this->command->warn('mock/stations.json not found');
            return;
        }
        $json = json_decode(File::get($path), true);
        foreach ($json as $item) {
            Station::updateOrCreate(['id' => $item['id']], [
                'name' => $item['name'] ?? null,
                'image' => $item['image'] ?? null,
                'address' => $item['address'] ?? null,
                'lat' => data_get($item, 'location.lat'),
                'lng' => data_get($item, 'location.lng'),
            ]);
        }
    }
}
