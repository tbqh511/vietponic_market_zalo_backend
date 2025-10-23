<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $banners = Banner::orderBy('position', 'asc')->get();
        return response()->json($banners);
    }
}
