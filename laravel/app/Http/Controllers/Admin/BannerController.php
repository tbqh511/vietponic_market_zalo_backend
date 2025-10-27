<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $data = $request->validate([
            'image' => 'nullable|string|max:1024',
            'image_file' => 'nullable|image|max:5120', // max 5MB
        ]);

        $imageUrl = $request->input('image');
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('banners', 'public');
            $imageUrl = Storage::url($path);
        }

        Banner::create([
            'image' => $imageUrl,
        ]);

        return redirect()->route('banners.index')->with('success', 'Banner created');
    }

    public function edit($id)
    {
        $banner = Banner::findOrFail($id);

        $metadata = null;
        if ($banner->image) {
            // try to map the stored URL to storage disk path
            $relative = preg_replace('#^/storage/#', '', $banner->image);
            // if image contains full URL, attempt to remove asset base
            $relative = preg_replace('#^https?://[^/]+/storage/#', '', $relative);
            if ($relative && Storage::disk('public')->exists($relative)) {
                $diskPath = Storage::disk('public')->path($relative);
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
        }

        return view('admin.banners.edit', compact('banner', 'metadata'));
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $data = $request->validate([
            'image' => 'nullable|string|max:1024',
            'image_file' => 'nullable|image|max:5120',
        ]);

        $imageUrl = $request->input('image', $banner->image);
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('banners', 'public');
            $imageUrl = Storage::url($path);
        }

        $banner->update([
            'image' => $imageUrl,
        ]);

        return redirect()->route('banners.index')->with('success', 'Banner updated');
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();
        return redirect()->route('banners.index')->with('success', 'Banner deleted');
    }
}
