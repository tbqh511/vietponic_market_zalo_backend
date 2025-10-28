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
        $request->validate([
            'category_id' => 'nullable|exists:zalo_categories,id',
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'image_file' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
            'image' => 'nullable|string|max:1024',
            'detail' => 'nullable|string',
        ]);

        // Check if at least one image source is provided
        if (!$request->hasFile('image_file') && !$request->filled('image')) {
            return back()->withErrors(['image' => 'Please upload an image file or provide an image URL.']);
        }

        $imagePath = null;

        if ($request->hasFile('image_file')) {
            $tempPath = $request->file('image_file')->getRealPath();
            $imagePath = $this->processImage($tempPath);
        } elseif ($request->filled('image')) {
            // Validate URL
            $url = $request->input('image');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return back()->withErrors(['image' => 'Invalid URL format']);
            }
            // Download image from URL
            $tempPath = tempnam(sys_get_temp_dir(), 'product');
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; Product Upload)',
                ],
            ]);
            $data = file_get_contents($url, false, $context);
            if ($data === false) {
                return back()->withErrors(['image' => 'Failed to download image from URL']);
            }
            file_put_contents($tempPath, $data);
            $imagePath = $this->processImage($tempPath);
            unlink($tempPath);
        }

        // generate id similar to categories (mock uses explicit ids)
        $max = ZaloProduct::max('id');
        $id = ($max ? $max : 0) + 1;

        ZaloProduct::create([
            'id' => $id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'price' => $request->price ?? 0,
            'original_price' => $request->original_price,
            'image' => $imagePath,
            'detail' => $request->detail,
        ]);

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
            'image_file' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
            'image' => 'nullable|string|max:1024',
            'detail' => 'nullable|string',
        ]);

        $imagePath = $product->image;

        if ($request->hasFile('image_file')) {
            // Delete old image
            if ($product->image && File::exists(public_path($product->image))) {
                File::delete(public_path($product->image));
            }
            $tempPath = $request->file('image_file')->getRealPath();
            $imagePath = $this->processImage($tempPath);
        } elseif ($request->filled('image') && $request->input('image') !== $product->image) {
            // Validate URL
            $url = $request->input('image');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return back()->withErrors(['image' => 'Invalid URL format']);
            }
            // Delete old image
            if ($product->image && File::exists(public_path($product->image))) {
                File::delete(public_path($product->image));
            }
            // Download new image
            $tempPath = tempnam(sys_get_temp_dir(), 'product');
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; Product Upload)',
                ],
            ]);
            $data = file_get_contents($url, false, $context);
            if ($data === false) {
                return back()->withErrors(['image' => 'Failed to download image from URL']);
            }
            file_put_contents($tempPath, $data);
            $imagePath = $this->processImage($tempPath);
            unlink($tempPath);
        }

        $product->update([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'price' => $request->price ?? 0,
            'original_price' => $request->original_price,
            'image' => $imagePath,
            'detail' => $request->detail,
        ]);

        return redirect()->route('zalo-products.index')->with('success', 'Product updated');
    }

    public function destroy($id)
    {
        $product = ZaloProduct::findOrFail($id);
        // Delete old image if exists
        if ($product->image && File::exists(public_path($product->image))) {
            File::delete(public_path($product->image));
        }
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

        // Target dimensions
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
