<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('id')->get();
        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
        ], [
            'image_file.mimes' => 'Định dạng ảnh không hỗ trợ. Chỉ chấp nhận JPEG, PNG, GIF hoặc WEBP.',
            'image_file.image' => 'Tệp tải lên phải là ảnh.',
            'image_file.max'   => 'Kích thước ảnh tối đa 5MB.',
        ]);

        // Check if at least one image source is provided
        if (!$request->hasFile('image_file') && !$request->filled('image')) {
            return back()->withErrors(['image' => 'Please upload an image file or provide an image URL.']);
        }

        $result = null;

        if ($request->hasFile('image_file')) {
            $tempPath = $request->file('image_file')->getRealPath();
            $result = $this->processImage($tempPath);
        } elseif ($request->filled('image')) {
            // Validate URL
            $url = $request->input('image');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return back()->withErrors(['image' => 'Invalid URL format']);
            }
            // Download image from URL
            $tempPath = tempnam(sys_get_temp_dir(), 'banner');
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; Banner Upload)',
                ],
            ]);
            $data = file_get_contents($url, false, $context);
            if ($data === false) {
                return back()->withErrors(['image' => 'Failed to download image from URL']);
            }
            file_put_contents($tempPath, $data);
            $result = $this->processImage($tempPath);
            unlink($tempPath);
        }

        Banner::create([
            'image'            => $result['path'],
            'intrinsic_width'  => $result['intrinsic_width'],
            'intrinsic_height' => $result['intrinsic_height'],
            'intrinsic_aspect' => $result['intrinsic_aspect'],
        ]);

        return redirect()->route('banners.index')->with('success', 'Banner created');
    }

    public function edit($id)
    {
        $banner = Banner::findOrFail($id);

        $metadata = null;
        if ($banner->image && File::exists(public_path($banner->image))) {
            $diskPath = public_path($banner->image);
            $info = @getimagesize($diskPath);
            if ($info) {
                $intrinsicW = $info[0];
                $intrinsicH = $info[1];
                // choose rendered height same as index preview (40px) for reference
                $renderedH = 40;
                $renderedW = intval(round($intrinsicW * ($renderedH / $intrinsicH)));

                // helper to compute simplified ratio
                $gcd = function ($a, $b) use (&$gcd) {
                    return $b == 0 ? $a : $gcd($b, $a % $b);
                };
                $ri = $gcd($renderedW, $renderedH);
                $ii = $gcd($intrinsicW, $intrinsicH);

                $metadata = [
                    'rendered_width' => $renderedW,
                    'rendered_height' => $renderedH,
                    'rendered_ratio' => ($renderedW / $ri) . ':' . ($renderedH / $ri),
                    'intrinsic_width' => $intrinsicW,
                    'intrinsic_height' => $intrinsicH,
                    'intrinsic_ratio' => ($intrinsicW / $ii) . ':' . ($intrinsicH / $ii),
                ];
            }
        }

        return view('admin.banners.edit', compact('banner', 'metadata'));
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $data = $request->validate([
            'image' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
        ], [
            'image_file.mimes' => 'Định dạng ảnh không hỗ trợ. Chỉ chấp nhận JPEG, PNG, GIF hoặc WEBP.',
            'image_file.image' => 'Tệp tải lên phải là ảnh.',
            'image_file.max'   => 'Kích thước ảnh tối đa 5MB.',
        ]);

        $result = null;

        if ($request->hasFile('image_file')) {
            // Delete old image
            if ($banner->image && File::exists(public_path($banner->image))) {
                File::delete(public_path($banner->image));
            }
            $tempPath = $request->file('image_file')->getRealPath();
            $result = $this->processImage($tempPath);
        } elseif ($request->filled('image') && $request->input('image') !== $banner->image) {
            // Validate URL
            $url = $request->input('image');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return back()->withErrors(['image' => 'Invalid URL format']);
            }
            // Delete old image
            if ($banner->image && File::exists(public_path($banner->image))) {
                File::delete(public_path($banner->image));
            }
            // Download new image
            $tempPath = tempnam(sys_get_temp_dir(), 'banner');
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; Banner Upload)',
                ],
            ]);
            $data = file_get_contents($url, false, $context);
            if ($data === false) {
                return back()->withErrors(['image' => 'Failed to download image from URL']);
            }
            file_put_contents($tempPath, $data);
            $result = $this->processImage($tempPath);
            unlink($tempPath);
        }

        if ($result) {
            $banner->update([
                'image'            => $result['path'],
                'intrinsic_width'  => $result['intrinsic_width'],
                'intrinsic_height' => $result['intrinsic_height'],
                'intrinsic_aspect' => $result['intrinsic_aspect'],
            ]);
        }

        return redirect()->route('banners.index')->with('success', 'Banner updated');
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        // Delete old image if exists
        if ($banner->image && File::exists(public_path($banner->image))) {
            File::delete(public_path($banner->image));
        }
        $banner->delete();
        return redirect()->route('banners.index')->with('success', 'Banner deleted');
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

        // Target dimensions
        $targetWidth = 1312;
        $targetHeight = 708;

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
        $directory = public_path('images/banners');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Generate filename
        $filename = Str::random(40) . '.jpg';
        $path = $directory . '/' . $filename;

        // Save as JPEG at quality 82 — visually identical to 100 but ~5× smaller.
        imagejpeg($resized, $path, 82);

        // Free memory
        imagedestroy($source);
        imagedestroy($resized);

        return [
            'path'             => 'images/banners/' . $filename,
            'intrinsic_width'  => $targetWidth,
            'intrinsic_height' => $targetHeight,
            'intrinsic_aspect' => $targetWidth . ':' . $targetHeight,
        ];
    }
}
