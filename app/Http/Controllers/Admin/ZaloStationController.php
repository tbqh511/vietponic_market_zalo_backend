<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ZaloStationController extends Controller
{
    public function index()
    {
        $stations = Station::orderBy('id')->get();
        return view('admin.zalo_stations.index', compact('stations'));
    }

    public function create()
    {
        return view('admin.zalo_stations.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image_file' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:2048',
            'image' => 'nullable|string|max:1024',
            'address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'vtp_province_id' => 'nullable|integer|exists:vtp_provinces,id',
            'vtp_district_id' => 'nullable|integer|exists:vtp_districts,id',
            'vtp_ward_id' => 'nullable|integer|exists:vtp_wards,id',
            'vtp_address' => 'nullable|string|max:500',
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
            $tempPath = tempnam(sys_get_temp_dir(), 'station');
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; Station Upload)',
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

        // model uses non-incrementing id (imported from mock). Generate next id.
        $max = Station::max('id');
        $id = ($max ? $max : 0) + 1;

        Station::create([
            'id' => $id,
            'name' => $request->name,
            'image' => $imagePath,
            'address' => $request->address,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'vtp_province_id' => $request->input('vtp_province_id') ?: null,
            'vtp_district_id' => $request->input('vtp_district_id') ?: null,
            'vtp_ward_id' => $request->input('vtp_ward_id') ?: null,
            'vtp_address' => $request->input('vtp_address') ?: null,
        ]);

        Cache::forget('stations_with_vtp');

        return redirect()->route('zalo-stations.index')->with('success', 'Station created');
    }

    public function edit($id)
    {
        $station = Station::findOrFail($id);
        return view('admin.zalo_stations.edit', compact('station'));
    }

    public function update(Request $request, $id)
    {
        $station = Station::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'image_file' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:2048',
            'image' => 'nullable|string|max:1024',
            'address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'vtp_province_id' => 'nullable|integer|exists:vtp_provinces,id',
            'vtp_district_id' => 'nullable|integer|exists:vtp_districts,id',
            'vtp_ward_id' => 'nullable|integer|exists:vtp_wards,id',
            'vtp_address' => 'nullable|string|max:500',
        ]);

        $imagePath = $station->image;

        if ($request->hasFile('image_file')) {
            // Delete old image
            if ($station->image && File::exists(public_path($station->image))) {
                File::delete(public_path($station->image));
            }
            $tempPath = $request->file('image_file')->getRealPath();
            $imagePath = $this->processImage($tempPath);
        } elseif ($request->filled('image') && $request->input('image') !== $station->image) {
            // Validate URL
            $url = $request->input('image');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return back()->withErrors(['image' => 'Invalid URL format']);
            }
            // Delete old image
            if ($station->image && File::exists(public_path($station->image))) {
                File::delete(public_path($station->image));
            }
            // Download new image
            $tempPath = tempnam(sys_get_temp_dir(), 'station');
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; Station Upload)',
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

        $station->update([
            'name' => $request->name,
            'image' => $imagePath,
            'address' => $request->address,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'vtp_province_id' => $request->input('vtp_province_id') ?: null,
            'vtp_district_id' => $request->input('vtp_district_id') ?: null,
            'vtp_ward_id' => $request->input('vtp_ward_id') ?: null,
            'vtp_address' => $request->input('vtp_address') ?: null,
        ]);

        Cache::forget('stations_with_vtp');

        return redirect()->route('zalo-stations.index')->with('success', 'Station updated');
    }

    public function destroy($id)
    {
        $station = Station::findOrFail($id);
        // Delete old image if exists
        if ($station->image && File::exists(public_path($station->image))) {
            File::delete(public_path($station->image));
        }
        $station->delete();
        Cache::forget('stations_with_vtp');
        return redirect()->route('zalo-stations.index')->with('success', 'Station deleted');
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
        $targetWidth = 192;
        $targetHeight = 192;

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
        $directory = public_path('images/zalo_stations');
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

        return 'images/zalo_stations/' . $filename;
    }
}