<?php

namespace App\Http\Controllers;

use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use App\Models\Banner;
use App\Models\Station;
use App\Models\ZaloOrder;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $data = $query->orderBy('id')->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'price' => $product->price,
                'original_price' => $product->original_price,
                'image' => $product->image_url, // Use full URL from accessor
                'detail' => $product->detail,
            ];
        });
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function banners()
    {
        $data = Banner::orderBy('id')->get()->map(function ($banner) {
            return [
                'id' => $banner->id,
                'image' => $banner->image ? asset($banner->image) : null,
                'created_at' => $banner->created_at,
                'updated_at' => $banner->updated_at,
            ];
        });
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

    public function authenticate(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        $accessToken = $request->access_token;

        try {
            // Call Zalo Open API to get user profile
            $response = Http::withHeaders([
                'access_token' => $accessToken,
            ])->get(config('services.zalo.api_base_url') . '/v2.0/me');

            if (!$response->successful()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to get user profile from Zalo'
                ], 400);
            }

            $zaloProfile = $response->json();

            if (!isset($zaloProfile['id'])) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid Zalo profile response'
                ], 400);
            }

            // Find or create customer based on Zalo ID
            $customer = Customer::where('firebase_id', $zaloProfile['id'])->first();

            if (!$customer) {
                // Create new customer
                $customer = Customer::create([
                    'name' => $zaloProfile['name'] ?? 'Zalo User',
                    'email' => isset($zaloProfile['id']) ? $zaloProfile['id'] . '@zalo.user' : null,
                    'firebase_id' => $zaloProfile['id'],
                    'mobile' => null, // Will be updated when user provides phone
                    'profile' => null,
                    'address' => null,
                    'fcm_id' => null,
                    'logintype' => 'zalo',
                    'isActive' => 1,
                ]);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($customer);

            return response()->json([
                'error' => false,
                'message' => 'Authentication successful',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'profile' => $customer->profile,
                        'mobile' => $customer->mobile,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
