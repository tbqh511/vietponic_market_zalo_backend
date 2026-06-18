<?php

namespace Tests\Feature;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StationsApiTest — GET /api/stations (public).
 *
 * Kiểm tra rằng image URL được transform sang absolute URL khi API trả về:
 *  - Đường dẫn tương đối → prefix thành URL tuyệt đối bằng asset()
 *  - URL tuyệt đối đã có → giữ nguyên, không bị prefix thêm
 *  - image null → vẫn null
 */
class StationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_relative_image_path_becomes_absolute_url(): void
    {
        Station::create([
            'id'      => 1,
            'name'    => 'Trạm A',
            'address' => 'Địa chỉ A',
            'image'   => 'images/zalo_stations/test.jpg',
        ]);

        $response = $this->getJson('/api/stations');

        $response->assertOk();
        $image = $response->json('data.0.image');
        $this->assertNotNull($image);
        $this->assertStringStartsWith('http', $image);
        $this->assertStringEndsWith('/images/zalo_stations/test.jpg', $image);
    }

    public function test_absolute_image_url_is_not_modified(): void
    {
        Station::create([
            'id'      => 2,
            'name'    => 'Trạm B',
            'address' => 'Địa chỉ B',
            'image'   => 'https://cdn.example.com/photo.jpg',
        ]);

        $response = $this->getJson('/api/stations');

        $response->assertOk();
        $this->assertSame('https://cdn.example.com/photo.jpg', $response->json('data.0.image'));
    }

    public function test_null_image_remains_null(): void
    {
        Station::create([
            'id'      => 3,
            'name'    => 'Trạm C',
            'address' => 'Địa chỉ C',
            'image'   => null,
        ]);

        $response = $this->getJson('/api/stations');

        $response->assertOk();
        $this->assertNull($response->json('data.0.image'));
    }

    public function test_stations_ordered_by_id(): void
    {
        Station::create(['id' => 10, 'name' => 'Trạm Z', 'address' => 'Địa chỉ Z']);
        Station::create(['id' => 1,  'name' => 'Trạm A', 'address' => 'Địa chỉ A']);
        Station::create(['id' => 5,  'name' => 'Trạm M', 'address' => 'Địa chỉ M']);

        $response = $this->getJson('/api/stations');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertSame([1, 5, 10], $ids);
    }
}
