<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloProduct;
use App\Models\ZaloProductImage;
use App\Models\ZaloCategory;
use App\Models\ZaloUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ZaloProductController extends Controller
{
    public function index(Request $request)
    {
        $query = ZaloProduct::query()->with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', $search)
                  ->orWhereHas('category', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('price_min')) {
            $query->where('price', '>=', (int) $request->price_min);
        }
        if ($request->filled('price_max')) {
            $query->where('price', '<=', (int) $request->price_max);
        }

        $perPage = (int) $request->get('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50]) ? $perPage : 15;

        $products = $query->orderBy('id')->paginate($perPage)->withQueryString();
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = ZaloCategory::orderBy('id')->get();
        $units = ZaloUnit::active()->orderBy('label')->get();
        return view('admin.zalo_products.create', compact('categories', 'units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'        => 'required|exists:zalo_categories,id',
            'name'               => 'required|string|max:255',
            'price'              => 'nullable|numeric|min:0',
            'original_price'     => 'nullable|numeric|min:0',
            'images'             => 'required|array|min:1',
            'images.*'           => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'detail'             => 'nullable|string',
            'unit_id'            => 'required|exists:zalo_units,id',
            'system_unit'        => 'required|in:g,ml,piece',
            'conversion_factor'  => 'required|numeric|min:0.001',
            'weight'             => 'nullable|integer|min:1|max:50000',
            'length'             => 'nullable|integer|min:1|max:200',
            'width'              => 'nullable|integer|min:1|max:200',
            'height'             => 'nullable|integer|min:1|max:200',
        ]);

        $this->assertUnitConsistency($data);

        // Process uploaded images
        $uploadedPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                try {
                    $uploadedPaths[] = $this->processImage($file->getRealPath());
                } catch (\Throwable) {
                    // Rollback already-uploaded files
                    foreach ($uploadedPaths as $path) {
                        if (File::exists(public_path($path))) {
                            File::delete(public_path($path));
                        }
                    }
                    throw ValidationException::withMessages([
                        'images' => 'Một hoặc nhiều hình ảnh không hợp lệ hoặc không thể xử lý.',
                    ]);
                }
            }
        }

        $data['price'] = $data['price'] ?? 0;
        // Primary image = first uploaded (for backward compat & API)
        $data['image'] = $uploadedPaths[0] ?? null;
        unset($data['images']);

        DB::transaction(function () use ($data, $uploadedPaths) {
            $max = ZaloProduct::lockForUpdate()->max('id');
            $data['id'] = ((int) $max) + 1;
            $product = ZaloProduct::create($data);

            foreach ($uploadedPaths as $index => $path) {
                ZaloProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()->route('zalo-products.index')->with('success', 'Đã tạo sản phẩm');
    }

    public function edit($id)
    {
        $product = ZaloProduct::with('productImages')->findOrFail($id);
        $categories = ZaloCategory::orderBy('id')->get();
        $units = ZaloUnit::active()->orderBy('label')->get();
        return view('admin.zalo_products.edit', compact('product', 'categories', 'units'));
    }

    public function update(Request $request, $id)
    {
        $product = ZaloProduct::with('productImages')->findOrFail($id);

        $data = $request->validate([
            'category_id'        => 'nullable|exists:zalo_categories,id',
            'name'               => 'required|string|max:255',
            'price'              => 'nullable|numeric|min:0',
            'original_price'     => 'nullable|numeric|min:0',
            'new_images'         => 'nullable|array',
            'new_images.*'       => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'detail'             => 'nullable|string',
            'unit_id'            => 'nullable|exists:zalo_units,id',
            'system_unit'        => 'required|in:g,ml,piece',
            'conversion_factor'  => 'required|numeric|min:0.001',
            'weight'             => 'nullable|integer|min:1|max:50000',
            'length'             => 'nullable|integer|min:1|max:200',
            'width'              => 'nullable|integer|min:1|max:200',
            'height'             => 'nullable|integer|min:1|max:200',
        ]);

        $this->assertUnitConsistency($data);
        unset($data['new_images']);

        // Upload new images
        if ($request->hasFile('new_images')) {
            $nextOrder = $product->productImages->max('sort_order') + 1;
            foreach ($request->file('new_images') as $file) {
                try {
                    $path = $this->processImage($file->getRealPath());
                    ZaloProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'sort_order' => $nextOrder++,
                    ]);
                } catch (\Throwable $e) {
                    throw ValidationException::withMessages([
                        'new_images' => 'Một hoặc nhiều hình ảnh không hợp lệ hoặc không thể xử lý.',
                    ]);
                }
            }
        }

        // Sync primary image (first in sort order) to zalo_products.image
        $product->refresh();
        $firstImage = $product->productImages()->orderBy('sort_order')->orderBy('id')->first();
        $data['image'] = $firstImage?->image_path ?? $product->image;

        $product->update($data);
        return redirect()->route('zalo-products.edit', $product->id)
            ->with('success', 'Đã cập nhật sản phẩm');
    }

    public function destroy($id)
    {
        $product = ZaloProduct::findOrFail($id);
        // Delete all associated image files
        foreach ($product->productImages as $img) {
            if (File::exists(public_path($img->image_path))) {
                File::delete(public_path($img->image_path));
            }
        }
        if ($product->image && File::exists(public_path($product->image))) {
            File::delete(public_path($product->image));
        }
        $product->delete();
        return redirect()->route('zalo-products.index')->with('success', 'Đã xóa sản phẩm');
    }

    // DELETE /zalo-products/{product}/images/{image}
    public function destroyImage(Request $request, $productId, $imageId)
    {
        $product = ZaloProduct::with('productImages')->findOrFail($productId);
        $image = ZaloProductImage::where('product_id', $productId)->findOrFail($imageId);

        // Delete file
        if (File::exists(public_path($image->image_path))) {
            File::delete(public_path($image->image_path));
        }
        $image->delete();

        // Sync primary image after deletion
        $product->refresh();
        $firstImage = $product->productImages()->orderBy('sort_order')->orderBy('id')->first();
        if ($firstImage) {
            $product->update(['image' => $firstImage->image_path]);
        } else {
            $product->update(['image' => null]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Đã xóa ảnh');
    }

    // POST /zalo-products/{product}/images/reorder
    public function reorderImages(Request $request, $productId)
    {
        ZaloProduct::findOrFail($productId);

        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:zalo_product_images,id',
        ]);

        foreach ($request->order as $sortOrder => $imageId) {
            ZaloProductImage::where('product_id', $productId)
                ->where('id', $imageId)
                ->update(['sort_order' => $sortOrder]);
        }

        // Sync primary image
        $first = ZaloProductImage::where('product_id', $productId)
            ->orderBy('sort_order')->orderBy('id')->first();
        if ($first) {
            ZaloProduct::where('id', $productId)->update(['image' => $first->image_path]);
        }

        return response()->json(['ok' => true]);
    }

    private function assertUnitConsistency(array &$data): void
    {
        if (empty($data['unit_id'])) {
            return;
        }
        $unit = ZaloUnit::find($data['unit_id']);
        if ($unit) {
            $data['system_unit'] = $unit->system_unit_type;
        }
    }

    private function processImage($imagePath)
    {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new \Exception('Invalid image');
        }

        $mime = $imageInfo['mime'];
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($imagePath);
                break;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new \Exception('Server chưa bật hỗ trợ WEBP (GD --with-webp). Vui lòng dùng JPEG/PNG.');
                }
                $source = imagecreatefromwebp($imagePath);
                break;
            default:
                throw new \Exception('Định dạng ảnh không hỗ trợ: ' . $mime);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = 560;
        $targetHeight = 560;

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($mime == 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        $srcX = 0;
        $srcY = 0;
        $srcW = $width;
        $srcH = $height;

        $ratio = max($targetWidth / $width, $targetHeight / $height);
        $newWidth = $width * $ratio;
        $newHeight = $height * $ratio;

        if ($newWidth > $targetWidth) {
            $srcX = ($newWidth - $targetWidth) / 2 / $ratio;
            $srcW = $targetWidth / $ratio;
        }
        if ($newHeight > $targetHeight) {
            $srcY = ($newHeight - $targetHeight) / 2 / $ratio;
            $srcH = $targetHeight / $ratio;
        }

        imagecopyresampled($resized, $source, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $srcW, $srcH);

        $directory = public_path('images/zalo_products');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = Str::random(40) . '.jpg';
        $path = $directory . '/' . $filename;
        imagejpeg($resized, $path, 100);

        imagedestroy($source);
        imagedestroy($resized);

        return 'images/zalo_products/' . $filename;
    }
}
