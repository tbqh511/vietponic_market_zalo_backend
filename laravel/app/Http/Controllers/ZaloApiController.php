<?php

namespace App\Http\Controllers;

use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use App\Models\Banner;
use App\Models\Station;
use App\Models\ZaloOrder;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ZaloApiController extends Controller
{
    public function categories()
    {
        $data = ZaloCategory::orderBy('id')->get();
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function products(Request $request)
    {
        $query = ZaloProduct::query();
        if ($request->has('categoryId')) {
            $query->where('category_id', $request->categoryId);
        }
        $data = $query->orderBy('id')->get();
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function banners()
    {
        $data = Banner::orderBy('id')->get();
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function stations()
    {
        $data = Station::orderBy('id')->get();
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function orders(Request $request)
    {
        // require JWT Bearer token
        $header = $request->header('Authorization', '');
        if (!
            \Illuminate\Support\Str::startsWith($header, 'Bearer ')
        ) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        }

        try {
            $token = \Illuminate\Support\Str::substr($header, 7);
            $payload = JWTAuth::getPayload($token);
            $customerId = $payload['customer_id'] ?? null;
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'Invalid token'], 401);
        }

        $orders = ZaloOrder::with(['items', 'delivery'])->where('customer_id', $customerId)->orderBy('id', 'desc')->get();
        return response()->json(['error' => false, 'data' => $orders]);
    }
}
