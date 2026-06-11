<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use App\Models\ZaloUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Batch B3 — PROD-01/02/03 (admin web tạo sản phẩm).
 *
 * Test ở mức HTTP qua route `zalo-products.store` (middleware auth+checklogin+language).
 * Auth bằng User active (status=1, type=0 admin) để qua CheckLogin.
 *
 * Khẳng định:
 *  - PROD-02: thiếu tên → message tiếng Việt "tên sản phẩm là bắt buộc" (KHÔNG còn tiếng Anh).
 *  - PROD-03: ảnh sai định dạng / >2MB → message tiếng Việt; không 500.
 *  - PROD-01: 2 sản phẩm liên tiếp KHÔNG trùng id; danh mục bắt buộc; flash tiếng Việt.
 */
class AdminProductCreateTest extends TestCase
{
    use RefreshDatabase;

    private ZaloCategory $category;
    private ZaloUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ZaloCategory::create([
            'id' => 1,
            'name' => 'Rau ăn lá',
        ]);

        // zalo_units đã được seed sẵn bởi migration 2026_05_15_110000 (code 'bo' = bó, g).
        $this->unit = ZaloUnit::where('code', 'bo')->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'status' => 1,
            'type' => 0,
            'permissions' => '',
        ]);
    }

    /**
     * Payload hợp lệ tối thiểu để tạo sản phẩm (đủ field required mới).
     */
    private function validPayload(array $override = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name' => 'Cải ngọt',
            'price' => 15000,
            'unit_id' => $this->unit->id,
            'system_unit' => 'g',
            'conversion_factor' => 100,
            'image' => UploadedFile::fake()->image('product.jpg', 600, 600),
        ], $override);
    }

    /** PROD-02 — thiếu tên → lỗi tiếng Việt "tên sản phẩm là bắt buộc". */
    public function test_missing_name_returns_vietnamese_required_message(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('zalo-products.store'), $this->validPayload(['name' => '']));

        $response->assertSessionHasErrors('name');

        $errors = session('errors')->get('name');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('tên sản phẩm là bắt buộc', $errors[0]);
        // Khẳng định KHÔNG còn message tiếng Anh mặc định.
        $this->assertStringNotContainsString('field is required', $errors[0]);
    }

    /** PROD-03a — ảnh sai định dạng (.txt) → lỗi tiếng Việt về hình ảnh, không 500. */
    public function test_invalid_image_format_returns_vietnamese_error(): void
    {
        $response = $this->actingAs($this->admin())->post(
            route('zalo-products.store'),
            $this->validPayload(['image' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain')])
        );

        $response->assertSessionHasErrors('image');

        $errors = session('errors')->get('image');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('hình ảnh', $errors[0]);
        $this->assertStringNotContainsString('must be', $errors[0]);
    }

    /** PROD-03b — ảnh >2MB → lỗi tiếng Việt (kilobyte), không 500. */
    public function test_oversized_image_returns_vietnamese_error(): void
    {
        $response = $this->actingAs($this->admin())->post(
            route('zalo-products.store'),
            // size() tính theo KB → 3000KB ~ 2.9MB > 2048KB.
            $this->validPayload(['image' => UploadedFile::fake()->image('big.jpg')->size(3000)])
        );

        $response->assertSessionHasErrors('image');

        $errors = session('errors')->get('image');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('kilobyte', $errors[0]);
    }

    /** PROD-01a — tạo 2 sản phẩm liên tiếp → id KHÁC nhau (không race/trùng). */
    public function test_two_consecutive_products_get_distinct_ids(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('zalo-products.store'), $this->validPayload(['name' => 'SP A']))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('zalo-products.store'), $this->validPayload(['name' => 'SP B']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ZaloProduct::count());
        $ids = ZaloProduct::pluck('id')->all();
        $this->assertCount(2, array_unique($ids), 'Hai sản phẩm phải có id khác nhau');
    }

    /** PROD-01b — thiếu danh mục → bị chặn (required). */
    public function test_missing_category_is_required(): void
    {
        $response = $this->actingAs($this->admin())->post(
            route('zalo-products.store'),
            $this->validPayload(['category_id' => ''])
        );

        $response->assertSessionHasErrors('category_id');
        $this->assertSame(0, ZaloProduct::count());
    }

    /** PROD-01c — tạo hợp lệ → flash tiếng Việt + sản phẩm xuất hiện. */
    public function test_successful_create_flashes_vietnamese_message(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('zalo-products.store'), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Đã tạo sản phẩm');
        $this->assertDatabaseHas('zalo_products', ['name' => 'Cải ngọt']);
    }

    /**
     * PROD-03 (no-500) — tệp có mime image/jpeg (qua được rule image|mimes) nhưng
     * nội dung hỏng → getimagesize() fail trong processImage(). Phải nhận validation
     * error 'image' (redirect-back), TUYỆT ĐỐI không trả 500.
     */
    public function test_corrupt_image_does_not_500_and_returns_validation_error(): void
    {
        $corrupt = UploadedFile::fake()->createWithContent('x.jpg', "\xFF\xD8\xFFnotreallyjpeg");

        $response = $this->actingAs($this->admin())->post(
            route('zalo-products.store'),
            $this->validPayload(['image' => $corrupt])
        );

        // KHÔNG 500: redirect-back kèm lỗi validation, không phải server error.
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertSessionHasErrors('image');
        $this->assertSame(0, ZaloProduct::count());
    }
}
