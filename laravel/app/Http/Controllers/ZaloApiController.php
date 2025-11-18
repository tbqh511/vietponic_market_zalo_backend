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
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        // $header = $request->header('Authorization', '');
        // if (!\Illuminate\Support\Str::startsWith($header, 'Bearer ')) {
        //     return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        // }

        // try {
        //     $token = \Illuminate\Support\Str::substr($header, 7);
        //     $payload = JWTAuth::getPayload($token);
        //     $customerId = $payload['sub'] ?? null;
        // } catch (\Exception $e) {
        //     return response()->json(['error' => true, 'message' => 'Invalid token'], 401);
        // }

        $request->validate([
            'customer_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|string',
            'items.*.quantity' => 'required|string',
            'items.*.image' => 'nullable|string',
            'items.*.detail' => 'nullable|string',
            'delivery' => 'required|array',
            'delivery.type' => 'required|string|in:shipping,pickup',
            'delivery.address' => 'required|string',
            'delivery.name' => 'required|string',
            'delivery.phone' => 'nullable|string',
            'delivery.station_id' => 'nullable|string',
            'total' => 'required|string',
            'note' => 'nullable|string',
            'created_at' => 'required|string',
        ]);

        $items = $request->items;
        $delivery = $request->delivery;
        $note = $request->note ?? '';
        $customerId = $request->customer_id;
        $total = $request->total;
        
        // Create order
        $createdAt = Carbon::parse($request->created_at);
        $order = ZaloOrder::create([
            'status' => 'pending',
            'payment_status' => 'cod',
            'created_at' => $createdAt,
            'received_at' => $createdAt->copy()->addDays(3),
            'total' => $total,
            'note' => $note,
            'customer_id' => $customerId,
        ]);        // Create order items
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
            'alias' => '',
            'address' => $delivery['address'],
            'name' => $delivery['name'],
            'phone' => $delivery['phone'],
            'station_id' => $delivery['station_id'] ?? null,
            'station_name' => '',
            'station_image' => '',
            'lat' => null,
            'lng' => null,
        ]);

        $order->load(['items', 'delivery']);

        return response()->json([
            'message' => 'Đã tạo đơn hàng thành công!',
            'orderId' => $order->id,
        ], 201);
    }
    
    public function prepareOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'desc' => 'required|string',
            'item' => 'required|array',
            'item.*.id' => 'required|integer',
            'item.*.name' => 'nullable|string',
            'item.*.price' => 'nullable|numeric',
            'item.*.quantity' => 'nullable|integer|min:1',
            'item.*.amount' => 'required|numeric',
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
        // Lấy private key từ env
        $privateKey = env('ZALO_CHECK_OUT_SECRET');
        
        if (!$privateKey) {
            throw new \Exception('Missing ZALO_CHECK_OUT_SECRET in environment');
        }
        
        // Sắp xếp key theo thứ tự từ điển tăng dần
        ksort($params);
        
        // Tạo data string
        $dataMac = collect($params)
            ->map(function ($value, $key) {
                return $key . '=' . (is_array($value) || $value === null ? json_encode($value) : $value);
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
    public function zaloapiuser(Request $request)
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
    //HuyTBQ: Zalo Notify SDK Api
    public function notifySDK(Request $request)
    {
        try {
            $body = $request->all();
            $data = $body['data'] ?? null;
            $mac = $body['mac'] ?? null;

            if (!$data || !$mac) {
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Missing data or mac',
                ]);
            }

            $appId = $data['appId'] ?? null;
            $orderId = $data['orderId'] ?? null;
            $method = $data['method'] ?? null;

            if (!$appId || !$orderId || !$method) {
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Missing appId, orderId or method',
                ]);
            }

            // Validate method
            if (!in_array($method, ['COD','COD_SANDBOX', 'BANK','BANK_SANDBOX'])) {
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Invalid method',
                ]);
            }

            $secretKey = env('ZALO_APP_SECRET');
            if (!$secretKey) {
                Log::error('Missing ZALO_APP_SECRET in env');
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Server configuration error',
                ]);
            }

            $raw = "appId={$appId}&orderId={$orderId}&method={$method}";
            $expectedMac = hash_hmac('sha256', $raw, $secretKey);

            if (!hash_equals($mac, $expectedMac)) {
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Invalid MAC',
                ]);
            }

            // Find and update order
            $order = ZaloOrder::where('checkout_sdk_order_id', $orderId)->first();
            if (!$order) {
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Order not found',
                ]);
            }

            // Update payment method
            $order->update(['payment_method' => $method]);

            // Optionally update status if needed
            // $order->update(['status' => 'confirmed']); // Uncomment if needed

            return response()->json([
                'returnCode' => 1,
                'returnMessage' => 'Success',
            ]);

        } catch (\Exception $e) {
            Log::error('Notify SDK error: ' . $e->getMessage());
            return response()->json([
                'returnCode' => 0,
                'returnMessage' => 'Internal server error',
            ]);
        }
    }
    //HuyTBQ End: Zalo Notify SDK Api
    
    public function link(Request $request)
    {
        $request->validate([
            'orderId' => 'required|integer',
            'checkoutSdkOrderId' => 'required|string',
            'miniAppId' => 'required|string',
        ]);

        $order = ZaloOrder::find($request->orderId);
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Liên kết order với checkoutSdkOrderId
        $order->checkout_sdk_order_id = $request->checkoutSdkOrderId;
        $order->save();

        // Dispatch job để check status sau 20 phút
        \App\Jobs\CheckPaymentStatus::dispatch($order->id, $request->checkoutSdkOrderId, $request->miniAppId)
            ->delay(now()->addMinutes(20));

        return response()->json(['message' => 'Đã liên kết đơn hàng thành công!']);
    }
}
