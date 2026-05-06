<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloProduct;
use App\Models\ZaloCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ZaloProductController extends Controller
{
    public function index(Request $request)
    {
        $query = ZaloProduct::query();
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        $products = $query->orderBy('id')->with('category')->get();
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:zalo_categories,id',
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'detail' => 'nullable|string',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $tempPath = $request->file('image')->getRealPath();
            $imagePath = $this->processImage($tempPath);
            $data['image'] = $imagePath;
        }

        // generate id similar to categories (mock uses explicit ids)
        $max = ZaloProduct::max('id');
        $id = ($max ? $max : 0) + 1;
        $data['id'] = $id;
        $data['price'] = $data['price'] ?? 0;

        ZaloProduct::create($data);
        return redirect()->route('zalo-products.index')->with('success', 'Product created');
    }

    public function edit($id)
    {
        $product = ZaloProduct::findOrFail($id);
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $product = ZaloProduct::findOrFail($id);
        $data = $request->validate([
            'category_id' => 'nullable|exists:zalo_categories,id',
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'detail' => 'nullable|string',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image && File::exists(public_path($product->image))) {
                File::delete(public_path($product->image));
            }

            $tempPath = $request->file('image')->getRealPath();
            $imagePath = $this->processImage($tempPath);
            $data['image'] = $imagePath;
        }

        $product->update($data);
        return redirect()->route('zalo-products.index')->with('success', 'Product updated');
    }

    public function destroy($id)
    {
        $product = ZaloProduct::findOrFail($id);
        $product->delete();
        return redirect()->route('zalo-products.index')->with('success', 'Product deleted');
    }

    private function processImage($imagePath)
    {
        // Load image
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
            default:
                throw new \Exception('Unsupported image type');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // Target dimensions for products (560x560 as mentioned)
        $targetWidth = 560;
        $targetHeight = 560;

        // Create new image
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency for PNG
        if ($mime == 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        // Resize and crop to fit
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

        // Ensure directory exists
        $directory = public_path('images/zalo_products');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Generate filename
        $filename = Str::random(40) . '.jpg';
        $path = $directory . '/' . $filename;

        // Save as JPEG with maximum quality for best image quality
        imagejpeg($resized, $path, 100);

        // Free memory
        imagedestroy($source);
        imagedestroy($resized);

        return 'images/zalo_products/' . $filename;
    }
}
