<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user) return response()->json([], 200);

        $orders = Order::with(['items', 'delivery'])->where('created_by_user_id', $user->id)->get();
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        // Minimal placeholder: validate and return created order structure
        $request->validate([
            'items' => 'required|array|min:1',
        ]);

        $user = auth()->user();
        if (! $user) return response()->json(['message' => 'Unauthenticated'], 401);
        // create order transactionally
        $payload = $request->all();
        $orderId = null;
        DB::transaction(function () use ($payload, $user, &$orderId) {
            $orderId = DB::table('orders')->insertGetId([
                'status' => 'pending',
                'payment_status' => $payload['payment_status'] ?? 'unpaid',
                'created_at' => now(),
                'total' => isset($payload['total']) ? intval($payload['total']) : 0,
                'note' => $payload['note'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            if (! empty($payload['delivery'])) {
                $d = $payload['delivery'];
                $deliveryId = DB::table('deliveries')->insertGetId([
                    'order_id' => $orderId,
                    'type' => $d['type'] ?? null,
                    'alias' => $d['alias'] ?? null,
                    'address' => $d['address'] ?? null,
                    'name' => $d['name'] ?? null,
                    'phone' => $d['phone'] ?? null,
                    'station_id' => $d['station_id'] ?? null,
                    'station_image' => $d['station_image'] ?? null,
                    'location_lat' => isset($d['location']['lat']) ? floatval($d['location']['lat']) : null,
                    'location_lng' => isset($d['location']['lng']) ? floatval($d['location']['lng']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('orders')->where('id', $orderId)->update(['delivery_id' => $deliveryId]);
            }

            foreach ($payload['items'] as $it) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $it['product_id'] ?? null,
                    'product_snapshot' => json_encode($it['product_snapshot'] ?? []),
                    'quantity' => isset($it['quantity']) ? intval($it['quantity']) : 1,
                    'unit_price' => isset($it['unit_price']) ? intval($it['unit_price']) : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $created = Order::with(['items', 'delivery'])->find($orderId);
        return response()->json($created, 201);
    }
}
