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
use App\Services\StockService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ZaloApiController extends Controller
{
    public function __construct(
        private StockService $stockService,
        private RefundService $refundService,
    ) {}
    public function categories()
    {
        $data = ZaloCategory::orderBy('id')->get();
        return response()->json(['error' => false, 'data' => $data]);
    }

    public function products(Request $request)
    {
        $query = ZaloProduct::with(['category', 'unit']);
        if ($request->has('categoryId')) {
            $query->where('category_id', $request->categoryId);
        }
        $data = $query->orderBy('id')->get()->map(function ($product) {
            return [
                'id'                => $product->id,
                'category_id'       => $product->category_id,
                'category_name'     => $product->category?->name ?? 'Rau sạch',
                'name'              => $product->name,
                'price'             => $product->price,
                'original_price'    => $product->original_price,
                'image'             => $product->image_url,
                'detail'            => $product->detail,
                'unit_id'           => $product->unit_id,
                'unit_label'        => $product->unit?->label,
                'system_unit'       => $product->system_unit,
                'conversion_factor' => (float) $product->conversion_factor,
                'stock_available'   => $product->stock_available,
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

    //HuyTBQ: Order Apis (Protected by ZaloJwtMiddleware)
    public function index(Request $request)
    {
        // customer_id được gắn bởi ZaloJwtMiddleware
        $customerId = $request->attributes->get('zalo_customer_id');

        $query = ZaloOrder::with(['items', 'delivery'])
            ->where('customer_id', $customerId);
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        $orders = $query->orderBy('id', 'desc')->get();
        return response()->json(['error' => false, 'data' => $orders]);
    }

    public function show(Request $request, $id)
    {
        // customer_id được gắn bởi ZaloJwtMiddleware
        $customerId = $request->attributes->get('zalo_customer_id');

        $order = ZaloOrder::with(['items', 'delivery'])->where('id', $id)->where('customer_id', $customerId)->first();
        if (!$order) {
            return response()->json(['error' => true, 'message' => 'Order not found'], 404);
        }
        return response()->json(['error' => false, 'data' => $order]);
    }

    public function store(Request $request)
    {
        // customer_id được gắn bởi ZaloJwtMiddleware
        $customerId = $request->attributes->get('zalo_customer_id');

        $request->validate([
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
            'delivery.province_id' => 'nullable|integer',
            'delivery.district_id' => 'nullable|integer',
            'delivery.ward_id' => 'nullable|integer',
            'delivery.province_name' => 'nullable|string',
            'delivery.district_name' => 'nullable|string',
            'delivery.ward_name' => 'nullable|string',
            'total' => 'required|string',
            'subtotal' => 'nullable|string',
            'shipping_fee' => 'nullable|string',
            'shipping_service_code' => 'nullable|string|max:32',
            'shipping_service_name' => 'nullable|string',
            'note' => 'nullable|string',
            'created_at' => 'required|string',
        ]);

        $items = $request->items;
        $delivery = $request->delivery;
        $note = $request->note ?? '';

        // Server-side validate total: tính lại từ giá sản phẩm trong DB, ghi đè total từ client
        $productIds = collect($items)->pluck('product_id')->toArray();
        $products = ZaloProduct::whereIn('id', $productIds)->get()->keyBy('id');
        $serverTotal = 0;
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if ($product) {
                $serverTotal += $product->price * (int) $item['quantity'];
            } else {
                // Sản phẩm không tồn tại trong DB
                return response()->json([
                    'error' => true,
                    'message' => 'Sản phẩm ID ' . $item['product_id'] . ' không tồn tại',
                ], 422);
            }
        }

        // Shipping fee: server nhận từ client nhưng tự tính lại total để chống tamper
        $clientShippingFee = (int) ($request->shipping_fee ?? 0);
        $serverFinalTotal  = $serverTotal + $clientShippingFee;
        $clientTotal       = (float) $request->total;

        // Chênh lệch > 1000đ → reject (có thể client tự sửa total để giảm tiền ship)
        if (abs($clientTotal - $serverFinalTotal) > 1000) {
            Log::warning('Zalo Order total mismatch (shipping)', [
                'customer_id'       => $customerId,
                'client_total'      => $clientTotal,
                'server_subtotal'   => $serverTotal,
                'client_ship_fee'   => $clientShippingFee,
                'server_final'      => $serverFinalTotal,
            ]);
            return response()->json([
                'error'   => true,
                'message' => 'Tổng đơn hàng không hợp lệ. Vui lòng thử lại.',
            ], 422);
        }

        if (abs($clientTotal - $serverFinalTotal) > 0.01) {
            Log::info('Zalo Order total minor diff', [
                'customer_id'  => $customerId,
                'client_total' => $clientTotal,
                'server_total' => $serverFinalTotal,
            ]);
        }

        // Kiểm tra tồn kho trước khi tạo đơn
        $stockItems = collect($items)->map(fn ($i) => [
            'product_id' => $i['product_id'],
            'quantity'   => (int) $i['quantity'],
        ])->all();

        $stockCheck = $this->stockService->checkAvailability($stockItems);
        if ($stockCheck !== true) {
            return response()->json([
                'error'    => true,
                'message'  => 'Một số sản phẩm không đủ số lượng tồn kho',
                'shortages' => $stockCheck,
            ], 422);
        }

        // Bọc trong DB Transaction để tránh orphan data
        $order = DB::transaction(function () use ($items, $delivery, $note, $customerId, $serverTotal, $serverFinalTotal, $clientShippingFee, $request, $products) {
            $createdAt = Carbon::parse($request->created_at);

            // total = subtotal (giá SP từ DB) + shipping_fee (từ client, đã verify trên)
            $order = ZaloOrder::create([
                'status'                => 'pending',
                'payment_status'        => 'cod',
                'created_at'            => $createdAt,
                'received_at'           => $createdAt->copy()->addDays(3),
                'subtotal'              => $serverTotal,
                'shipping_fee'          => $clientShippingFee,
                'total'                 => $serverFinalTotal,
                'shipping_service_code' => $request->shipping_service_code ?? null,
                'shipping_service_name' => $request->shipping_service_name ?? null,
                'note'                  => $note,
                'customer_id'           => $customerId,
            ]);

            // Create order items (dùng giá + đơn vị từ DB, không tin payload client)
            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $product->loadMissing('unit');
                $qty = (int) $item['quantity'];
                $factor = (float) ($product->conversion_factor ?? 1);
                ZaloOrderItem::create([
                    'order_id'          => $order->id,
                    'product_id'        => $item['product_id'],
                    'name'              => $product->name,
                    'price'             => $product->price,
                    'quantity'          => $item['quantity'],
                    'image'             => $item['image'] ?? '',
                    'detail'            => $item['detail'] ?? '',
                    'unit_label'        => $product->unit?->label,
                    'system_unit'       => $product->system_unit ?? 'piece',
                    'conversion_factor' => $factor,
                    'system_total'      => $qty * $factor,
                ]);
            }

            // Create delivery (bao gồm VTP IDs để tính phí và hiển thị lại)
            ZaloDelivery::create([
                'order_id'      => $order->id,
                'type'          => $delivery['type'],
                'alias'         => '',
                'address'       => $delivery['address'],
                'name'          => $delivery['name'],
                'phone'         => $delivery['phone'] ?? null,
                'station_id'    => $delivery['station_id'] ?? null,
                'station_name'  => '',
                'station_image' => '',
                'lat'           => null,
                'lng'           => null,
                'province_id'   => $delivery['province_id'] ?? null,
                'district_id'   => $delivery['district_id'] ?? null,
                'ward_id'       => $delivery['ward_id'] ?? null,
                'province_name' => $delivery['province_name'] ?? null,
                'district_name' => $delivery['district_name'] ?? null,
                'ward_name'     => $delivery['ward_name'] ?? null,
            ]);

            return $order;
        });

        $order->load(['items', 'delivery']);

        // Đặt giữ tồn kho cho đơn hàng vừa tạo
        try {
            $this->stockService->reserveItems($order->id, $stockItems);
        } catch (\Throwable $e) {
            Log::error('reserveItems failed after order creation', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);
        }

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
        $request->validate([
            'status' => 'required|string|in:pending,confirmed,preparing,delivering,delivered,cancelled',
        ]);

        $order = ZaloOrder::find($id);
        if (!$order) {
            return response()->json(['error' => true, 'message' => 'Order not found'], 404);
        }

        $previousStatus = $order->status;
        $order->update(['status' => $request->status]);

        // Khi đơn bị huỷ: release stock + tính refund theo payment method
        if ($request->status === 'cancelled' && $previousStatus !== 'cancelled') {
            $order->update([
                'cancelled_at'         => now(),
                'cancelled_by'         => 'admin',
                'cancellation_reason'  => $request->input('cancellation_reason', 'Admin huỷ qua API'),
            ]);
            try {
                $this->stockService->releaseReservation($order->id);
            } catch (\Throwable $e) {
                Log::error('releaseReservation failed on cancellation', [
                    'order_id' => $order->id,
                    'message'  => $e->getMessage(),
                ]);
            }
            try {
                $this->refundService->processCancellationRefund($order, 'admin');
            } catch (\Throwable $e) {
                Log::error('processCancellationRefund failed on admin cancellation', [
                    'order_id' => $order->id,
                    'message'  => $e->getMessage(),
                ]);
            }
        }

        $order->load(['items', 'delivery']);

        return response()->json(['error' => false, 'data' => $order]);
    }

    /**
     * Customer huỷ đơn của chính mình. Chỉ cho phép khi status còn ở
     * pending / confirmed / preparing. Sau đó release stock + dispatch refund
     * theo payment method qua RefundService.
     */
    public function cancelByCustomer(Request $request, $id)
    {
        $request->validate([
            'reason_code' => 'nullable|string|max:64',
            'reason'      => 'nullable|string|max:500',
        ]);

        $customerId = $request->attributes->get('zalo_customer_id');

        try {
            $result = DB::transaction(function () use ($id, $customerId, $request) {
                $order = ZaloOrder::where('id', $id)->lockForUpdate()->first();
                if (!$order) {
                    return ['code' => 404, 'body' => ['error' => true, 'message' => 'Không tìm thấy đơn hàng']];
                }
                if ((int) $order->customer_id !== (int) $customerId) {
                    return ['code' => 403, 'body' => ['error' => true, 'message' => 'Bạn không có quyền huỷ đơn hàng này']];
                }

                // Idempotent: đã cancelled → trả về luôn, không double-release
                if ($order->status === 'cancelled') {
                    $order->load(['items', 'delivery']);
                    return ['code' => 200, 'body' => ['error' => false, 'data' => $order]];
                }

                if (!in_array($order->status, ['pending', 'confirmed', 'preparing'], true)) {
                    return ['code' => 422, 'body' => [
                        'error'   => true,
                        'message' => 'Đơn hàng đang ở trạng thái "' . $order->status . '" — không thể huỷ. Vui lòng liên hệ tổng đài để được hỗ trợ.',
                    ]];
                }

                $reasonCode = $request->input('reason_code', 'unspecified');
                $reasonText = trim((string) $request->input('reason', ''));
                $combinedReason = $reasonText !== ''
                    ? "[{$reasonCode}] {$reasonText}"
                    : "[{$reasonCode}]";

                $order->update([
                    'status'              => 'cancelled',
                    'cancelled_at'        => now(),
                    'cancelled_by'        => 'customer',
                    'cancellation_reason' => mb_substr($combinedReason, 0, 500),
                ]);

                return ['code' => 0, 'order' => $order];
            });
        } catch (\Throwable $e) {
            Log::error('cancelByCustomer: transaction failed', [
                'order_id'    => $id,
                'customer_id' => $customerId,
                'message'     => $e->getMessage(),
            ]);
            return response()->json(['error' => true, 'message' => 'Huỷ đơn thất bại, vui lòng thử lại'], 500);
        }

        // Sớm trả về cho các early-exit (404/403/422/idempotent 200)
        if (($result['code'] ?? 0) !== 0) {
            return response()->json($result['body'], $result['code']);
        }

        /** @var ZaloOrder $order */
        $order = $result['order'];

        // Sau khi đã chuyển status sang cancelled trong transaction → release + refund
        try {
            $this->stockService->releaseReservation($order->id);
        } catch (\Throwable $e) {
            Log::error('releaseReservation failed on customer cancellation', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);
        }
        try {
            $this->refundService->processCancellationRefund($order, 'customer');
        } catch (\Throwable $e) {
            Log::error('processCancellationRefund failed on customer cancellation', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);
        }

        $order->refresh()->load(['items', 'delivery']);
        return response()->json(['error' => false, 'data' => $order]);
    }

    /**
     * Admin xác nhận đã hoàn tiền thủ công (Bank/MoMo). Chuyển refund_status
     * pending_manual → refunded.
     */
    public function confirmManualRefund(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $order = ZaloOrder::find($id);
        if (!$order) {
            return response()->json(['error' => true, 'message' => 'Order not found'], 404);
        }

        if ($order->refund_status !== 'pending_manual') {
            return response()->json([
                'error'   => true,
                'message' => 'Đơn hàng không ở trạng thái chờ hoàn tiền thủ công (hiện tại: ' . ($order->refund_status ?? 'null') . ')',
            ], 422);
        }

        $this->refundService->confirmManualRefund($order, $request->input('note'));
        $order->load(['items', 'delivery']);
        return response()->json(['error' => false, 'data' => $order]);
    }
    //HuyTBQ End: Order Apis 
    //HuyTBQ: Zalo Auth Apis
    public function authenticate(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'phone_token'  => 'nullable|string',
        ]);

        $accessToken = $request->access_token;
        $phoneToken  = $request->phone_token;
        $secretKey = config('services.zalo.app_secret');

        try {
            // Call Zalo Graph API to get user profile (same pattern as /get-location)
            $response = Http::timeout(10)->withHeaders([
                'access_token' => $accessToken,
                'secret_key'   => $secretKey,
            ])->get('https://graph.zalo.me/v2.0/me?fields=id,name,picture');

            \Log::info('[authenticate] Zalo API response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Failed to get user profile from Zalo',
                    'zalo_status' => $response->status(),
                    'zalo_body'   => $response->body(),
                ], 400);
            }

            $zaloProfile = $response->json();

            if (!isset($zaloProfile['id'])) {
                return response()->json([
                    'error'        => true,
                    'message'      => 'Invalid Zalo profile response',
                    'zalo_profile' => $zaloProfile,
                ], 400);
            }

            // Decode phone token nếu có
            $phoneNumber = null;
            if ($phoneToken) {
                $phoneResponse = Http::timeout(10)->withHeaders([
                    'access_token' => $accessToken,
                    'code'         => $phoneToken,
                    'secret_key'   => $secretKey,
                ])->get('https://graph.zalo.me/v2.0/me/info');

                if ($phoneResponse->successful()) {
                    $phoneNumber = $phoneResponse->json('data.number');
                }

                \Log::info('[authenticate] Phone decode response', [
                    'status' => $phoneResponse->status(),
                    'number' => $phoneNumber,
                ]);
            }

            // Find or create customer based on Zalo ID
            $customer = Customer::where('firebase_id', $zaloProfile['id'])->first();

            if (!$customer) {
                $customer = Customer::create([
                    'name'        => $zaloProfile['name'] ?? 'Zalo User',
                    'email'       => $zaloProfile['id'] . '@zalo.user',
                    'firebase_id' => $zaloProfile['id'],
                    'mobile'      => $phoneNumber,
                    'profile'     => null,
                    'address'     => null,
                    'fcm_id'      => null,
                    'logintype'   => 'zalo',
                    'isActive'    => 1,
                ]);
            } elseif ($phoneNumber && !$customer->mobile) {
                // Cập nhật số điện thoại nếu chưa có
                $customer->update(['mobile' => $phoneNumber]);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($customer);

            return response()->json([
                'error' => false,
                'message' => 'Authentication successful',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id'              => $customer->id,
                        'name'            => $customer->name,
                        'email'           => $customer->email,
                        'profile'         => $customer->profile,
                        'mobile'          => $customer->mobile,
                        'is_farm_partner' => $customer->farmPartner()->where('status', 'active')->exists(),
                    ]
                ]
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Không thể kết nối đến Zalo để xác thực. Vui lòng thử lại.'
            ], 503);
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
            Log::info('Zalo notifySDK raw payload', ['body' => $body]);
            $data = $body['data'] ?? null;
            $overallMac = $body['overallMac'] ?? ($body['mac'] ?? null);

            if (!$data || !$overallMac) {
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
            if (!in_array($method, ['COD','COD_SANDBOX', 'BANK','BANK_SANDBOX', 'ZALOPAY','ZALOPAY_SANDBOX', 'MOMO','MOMO_SANDBOX'])) {
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Invalid method',
                ]);
            }

            // Webhook MAC dùng ZALO_CHECK_OUT_SECRET (giống prepare-order),
            // không phải ZALO_APP_SECRET — verified bằng cách compute offline với payload thật.
            $secretKey = env('ZALO_CHECK_OUT_SECRET');
            if (!$secretKey) {
                Log::error('Missing ZALO_CHECK_OUT_SECRET in env');
                return response()->json([
                    'returnCode' => 0,
                    'returnMessage' => 'Server configuration error',
                ]);
            }

            // overallMac = HMAC-SHA256( ksort(data) → "k1=v1&k2=v2&...", secret )
            $sortedData = $data;
            ksort($sortedData);
            $rawParts = [];
            foreach ($sortedData as $k => $v) {
                $rawParts[] = "{$k}={$v}";
            }
            $raw = implode('&', $rawParts);
            $expectedMac = hash_hmac('sha256', $raw, $secretKey);

            if (!hash_equals($overallMac, $expectedMac)) {
                Log::warning('notifySDK: Invalid MAC', [
                    'orderId' => $orderId,
                    'expected' => $expectedMac,
                    'received' => $overallMac,
                ]);
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

            // resultCode=1 thành công, -1 thất bại; nếu thiếu coi như success vì Zalo chỉ gọi /notify khi xong.
            $resultCode = $data['resultCode'] ?? 1;
            $paymentStatus = ((int) $resultCode === 1) ? 'success' : 'failed';

            $order->update([
                'payment_method' => $method,
                'payment_status' => $paymentStatus,
            ]);

            if ($paymentStatus === 'success') {
                event(new \App\Events\OrderPaymentSucceeded($order->id));
            }

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

        Log::info('Zalo link payload', [
            'orderId' => $request->orderId,
            'checkoutSdkOrderId' => $request->checkoutSdkOrderId,
            'miniAppId' => $request->miniAppId,
        ]);

        // Liên kết order với checkoutSdkOrderId và đánh dấu đang chờ xác nhận
        $order->checkout_sdk_order_id = $request->checkoutSdkOrderId;
        $order->payment_status = 'pending';
        $order->save();

        // Safety net: poll Zalo nhanh phòng trường hợp webhook /notify không tới.
        // Job tự reschedule (30s → 2min → 10min) nếu vẫn pending.
        \App\Jobs\CheckPaymentStatus::dispatch($order->id, $request->checkoutSdkOrderId, $request->miniAppId)
            ->delay(now()->addSeconds(30));

        return response()->json(['message' => 'Đã liên kết đơn hàng thành công!']);
    }
}
