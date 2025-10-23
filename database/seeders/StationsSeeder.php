<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StationsSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/stations.json');
        if (! file_exists($path)) return;

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) return;

        DB::table('stations')->truncate();

        foreach ($data as $item) {
            DB::table('stations')->insert([
                'id' => $item['id'] ?? null,
                'name' => $item['name'] ?? null,
                'image' => $item['image'] ?? null,
                'address' => $item['address'] ?? null,
                'location_lat' => isset($item['location']['lat']) ? floatval($item['location']['lat']) : null,
                'location_lng' => isset($item['location']['lng']) ? floatval($item['location']['lng']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
