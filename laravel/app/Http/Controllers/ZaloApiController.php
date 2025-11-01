<?php

namespace App\Http\Controllers;

use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use App\Models\Banner;
use App\Models\Station;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloDelivery;
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

    //HuyTBQ End: Order Apis 
    public function index(Request $request)
    {
        // // Require JWT Bearer token
        // $header = $request->header('Authorization', '');
        // if (!\Illuminate\Support\Str::startsWith($header, 'Bearer ')) {
        //     return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        // }

        // try {
        //     $token = \Illuminate\Support\Str::substr($header, 7);
        //     $payload = JWTAuth::getPayload($token);
        //     $customerId = $payload['customer_id'] ?? null;
        // } catch (\Exception $e) {
        //     return response()->json(['error' => true, 'message' => 'Invalid token'], 401);
        // }

        $query = ZaloOrder::with(['items', 'delivery']);
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        $orders = $query->orderBy('id', 'desc')->get();
        return response()->json(['error' => false, 'data' => $orders]);
    }

    public function show(Request $request, $id)
    {
        // Require JWT Bearer token
        $header = $request->header('Authorization', '');
        if (!\Illuminate\Support\Str::startsWith($header, 'Bearer ')) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        }

        try {
            $token = \Illuminate\Support\Str::substr($header, 7);
            $payload = JWTAuth::getPayload($token);
            $customerId = $payload['sub'] ?? null;
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'Invalid token'], 401);
        }

        $order = ZaloOrder::with(['items', 'delivery'])->where('id', $id)->where('customer_id', $customerId)->first();
        if (!$order) {
            return response()->json(['error' => true, 'message' => 'Order not found'], 404);
        }
        return response()->json(['error' => false, 'data' => $order]);
    }

    public function store(Request $request)
    {
        // Require JWT Bearer token
        $header = $request->header('Authorization', '');
        if (!\Illuminate\Support\Str::startsWith($header, 'Bearer ')) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        }

        try {
            $token = \Illuminate\Support\Str::substr($header, 7);
            $payload = JWTAuth::getPayload($token);
            $customerId = $payload['sub'] ?? null;
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'Invalid token'], 401);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.image' => 'nullable|string',
            'items.*.detail' => 'nullable|string',
            'delivery' => 'required|array',
            'delivery.type' => 'required|string',
            'delivery.alias' => 'nullable|string',
            'delivery.address' => 'required|string',
            'delivery.name' => 'required|string',
            'delivery.phone' => 'required|string',
            'delivery.station_id' => 'nullable|integer',
            'delivery.station_name' => 'nullable|string',
            'delivery.station_image' => 'nullable|string',
            'delivery.lat' => 'nullable|numeric',
            'delivery.lng' => 'nullable|numeric',
            'note' => 'nullable|string',
        ]);

        $items = $request->items;
        $delivery = $request->delivery;
        $note = $request->note ?? '';

        // Calculate total
        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        // Create order
        $order = ZaloOrder::create([
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'created_at' => now(),
            'received_at' => null,
            'total' => $total,
            'note' => $note,
            'customer_id' => $customerId,
        ]);

        // Create order items
        foreach ($items as $item) {
            ZaloOrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'image' => $item['image'] ?? '',
                'detail' => $item['detail'] ?? '',
            ]);
        }

        // Create delivery
        ZaloDelivery::create([
            'order_id' => $order->id,
            'type' => $delivery['type'],
            'alias' => $delivery['alias'] ?? '',
            'address' => $delivery['address'],
            'name' => $delivery['name'],
            'phone' => $delivery['phone'],
            'station_id' => $delivery['station_id'] ?? null,
            'station_name' => $delivery['station_name'] ?? '',
            'station_image' => $delivery['station_image'] ?? '',
            'lat' => $delivery['lat'] ?? null,
            'lng' => $delivery['lng'] ?? null,
        ]);

        $order->load(['items', 'delivery']);

        return response()->json(['error' => false, 'data' => $order], 201);
    }
    
    public function prepareOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'desc' => 'required|string',
            'item' => 'required|array',
            'item.*.id' => 'required|integer',
            'item.*.name' => 'required|string',
            'item.*.price' => 'required|numeric',
            'item.*.quantity' => 'required|integer|min:1',
            'extradata' => 'nullable',
            'method' => 'nullable|string',
        ]);

        $data = $request->only(['amount', 'desc', 'item', 'extradata', 'method']);
        
        // Tính toán MAC theo hướng dẫn Zalo
        $mac = $this->calculateMac($data);
        
        return response()->json([
            'error' => false,
            'mac' => $mac,
            'orderData' => $data
        ]);
    }

    private function calculateMac(array $params): string
    {
        // Lấy private key từ config
        $privateKey = config('services.zalo.app_secret');
        
        // Sắp xếp key theo thứ tự từ điển tăng dần
        ksort($params);
        
        // Tạo data string
        $dataMac = collect($params)
            ->map(function ($value, $key) {
                return $key . '=' . (is_array($value) ? json_encode($value) : $value);
            })
            ->implode('&');
        
        // Tính HMAC-SHA256
        return hash_hmac('sha256', $dataMac, $privateKey);
    }
    
    public function updateStatus(Request $request, $id)
    {
        // TODO: Add admin middleware here
        // This should be protected by admin-only middleware

        $request->validate([
            'status' => 'required|string|in:pending,confirmed,preparing,delivering,delivered,cancelled',
        ]);

        $order = ZaloOrder::find($id);
        if (!$order) {
            return response()->json(['error' => true, 'message' => 'Order not found'], 404);
        }

        $order->update(['status' => $request->status]);

        $order->load(['items', 'delivery']);

        return response()->json(['error' => false, 'data' => $order]);
    }
    //HuyTBQ End: Order Apis 
    //HuyTBQ: Zalo Auth Apis
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
    //HuyTBQ End: Zalo Auth Apis
    //HuyTBQ: Zalo Get User Phone Api
    public function infouser(Request $request)
    {
        // // Require JWT Bearer token to identify the customer
        // $header = $request->header('Authorization', '');
        // if (!\Illuminate\Support\Str::startsWith($header, 'Bearer ')) {
        //     return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        // }

        // try {
        //     $token = \Illuminate\Support\Str::substr($header, 7);
        //     $payload = JWTAuth::getPayload($token);
        //     $customerId = $payload['customer_id'] ?? null;

        //     if (!$customerId) {
        //         return response()->json(['error' => true, 'message' => 'Invalid token'], 401);
        //     }
        // } catch (\Exception $e) {
        //     return response()->json(['error' => true, 'message' => 'Invalid token'], 401);
        // }

        $request->validate([
            'access_token' => 'required|string',
            'code' => 'required|string', // Token từ API lấy số điện thoại
        ]);

        $accessToken = $request->access_token;
        $code = $request->code;
        $secretKey = config('services.zalo.app_secret');

        try {
            // Call Zalo Open API to get user phone number
            $response = Http::withHeaders([
                'access_token' => $accessToken,
                'code' => $code,
                'secret_key' => $secretKey,
            ])->get('https://graph.zalo.me/v2.0/me/info');

            if (!$response->successful()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to get user phone info from Zalo'
                ], 400);
            }

            $phoneData = $response->json();

            // Check if response contains phone number
            if (!isset($phoneData['data']['number'])) {
                return response()->json([
                    'error' => true,
                    'message' => 'Phone number not found in response'
                ], 400);
            }

            $phoneNumber = $phoneData['data']['number'];

            // Update customer phone number
            //$customer = Customer::find($customerId);
            //if ($customer) {
                //$customer->update(['mobile' => $phoneNumber]);
            //}

            return response()->json([
                'error' => false,
                'message' => 'Phone info retrieved and updated successfully',
                'data' => [
                    'number' => $phoneNumber
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to get phone info: ' . $e->getMessage()
            ], 500);
        }
    }
        //HuyTBQ End: Zalo Get User Phone Api
    //HuyTBQ: Zalo Get User Location Api
    public function getLocation(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'code' => 'required|string', // Location token từ Zalo Mini App
        ]);

        $accessToken = $request->access_token;
        $locationToken = $request->code;
        $secretKey = config('services.zalo.app_secret');

        try {
            // Call Zalo Open API to get user location info
            $response = Http::withHeaders([
                'access_token' => $accessToken,
                'code' => $locationToken,
                'secret_key' => $secretKey,
            ])->get('https://graph.zalo.me/v2.0/me/info');

            if (!$response->successful()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to get user location info from Zalo'
                ], 400);
            }

            $locationData = $response->json();

            // Check if response contains location data
            if (!isset($locationData['data'])) {
                return response()->json([
                    'error' => true,
                    'message' => 'Location data not found in response'
                ], 400);
            }

            $location = $locationData['data'];

            return response()->json([
                'error' => false,
                'message' => 'Location info retrieved successfully',
                'data' => [
                    'provider' => $location['provider'] ?? null,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'timestamp' => $location['timestamp'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to get location info: ' . $e->getMessage()
            ], 500);
        }
    }
    //HuyTBQ End: Zalo Get User Location Api
    
}
