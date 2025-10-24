<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrdersSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/orders.json');
        if (! file_exists($path)) return;

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) return;

    // disable foreign key checks to allow truncation on MySQL
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('order_items')->truncate();
    DB::table('deliveries')->truncate();
    DB::table('orders')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($data as $order) {
            $orderId = $order['id'] ?? null;
            // normalize keys (support camelCase from mock JSON)
            $createdAt = $order['created_at'] ?? $order['createdAt'] ?? now();
            $receivedAt = $order['received_at'] ?? $order['receivedAt'] ?? null;
            // Normalize ISO8601 datetimes (e.g. 2023-10-01T10:00:00Z) to MySQL datetime
            try {
                if (is_string($createdAt) && $createdAt !== '') {
                    $createdAt = Carbon::parse($createdAt)->toDateTimeString();
                }
            } catch (\Exception $e) {
                $createdAt = now();
            }
            try {
                if (is_string($receivedAt) && $receivedAt !== '') {
                    $receivedAt = Carbon::parse($receivedAt)->toDateTimeString();
                }
            } catch (\Exception $e) {
                $receivedAt = null;
            }
            $paymentStatus = $order['payment_status'] ?? $order['paymentStatus'] ?? 'unpaid';
            $total = isset($order['total']) ? intval($order['total']) : (isset($order['totalAmount']) ? intval($order['totalAmount']) : 0);

            // If an order with same id exists, remove it (and related items/delivery)
            if ($orderId) {
                DB::table('order_items')->where('order_id', $orderId)->delete();
                DB::table('deliveries')->where('order_id', $orderId)->delete();
                DB::table('orders')->where('id', $orderId)->delete();
            }

            $orderData = [
                'status' => $order['status'] ?? 'new',
                'payment_status' => $paymentStatus,
                'created_at' => $createdAt,
                'received_at' => $receivedAt,
                'total' => $total,
                'note' => $order['note'] ?? null,
                'delivery_id' => null,
                'created_by_user_id' => $order['created_by_user_id'] ?? $order['userId'] ?? null,
                'updated_at' => (isset($order['updated_at']) ? (is_string($order['updated_at']) ? (Carbon::parse($order['updated_at'])->toDateTimeString() ?? null) : $order['updated_at']) : (isset($order['updatedAt']) ? (is_string($order['updatedAt']) ? (Carbon::parse($order['updatedAt'])->toDateTimeString() ?? null) : $order['updatedAt']) : null)),
            ];

            // Only set explicit id when provided in JSON (avoid duplicate PKs / NULL insertion)
            if ($orderId) {
                $orderData['id'] = $orderId;
                // insert with explicit id (we already removed any existing rows above)
                DB::table('orders')->insert($orderData);
            } else {
                // let DB assign id and capture it
                $orderId = DB::table('orders')->insertGetId($orderData);
            }

            // delivery
            if (! empty($order['delivery'])) {
                $delivery = $order['delivery'];
                // Prefer not to insert explicit id to avoid PK conflicts; let auto-increment handle it
                // If mock delivery included an explicit delivery id, remove it to avoid duplicate PKs
                if (isset($delivery['id'])) {
                    DB::table('deliveries')->where('id', $delivery['id'])->delete();
                }

                $deliveryData = [
                    'order_id' => $orderId,
                    'type' => $delivery['type'] ?? null,
                    'alias' => $delivery['alias'] ?? null,
                    'address' => $delivery['address'] ?? null,
                    'name' => $delivery['name'] ?? null,
                    'phone' => $delivery['phone'] ?? null,
                    'station_id' => $delivery['station_id'] ?? null,
                    'station_image' => $delivery['station_image'] ?? null,
                    'location_lat' => isset($delivery['location']['lat']) ? floatval($delivery['location']['lat']) : null,
                    'location_lng' => isset($delivery['location']['lng']) ? floatval($delivery['location']['lng']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Insert and get new delivery id (do NOT set delivery.id from mock)
                $deliveryId = DB::table('deliveries')->insertGetId($deliveryData);

                // update order.delivery_id
                DB::table('orders')->where('id', $orderId)->update(['delivery_id' => $deliveryId]);
            }

            // items
            if (! empty($order['items']) && is_array($order['items'])) {
                foreach ($order['items'] as $item) {
                    // product_id can be in different shapes in mock data
                    $productId = $item['product_id'] ?? $item['productId'] ?? null;
                    if (! $productId && isset($item['product']) && is_array($item['product'])) {
                        $productId = $item['product']['id'] ?? $item['product']['productId'] ?? null;
                    }

                    // unit price fallback to item.product.price or product_snapshot.price
                    $unitPrice = null;
                    if (isset($item['unit_price'])) {
                        $unitPrice = intval($item['unit_price']);
                    } elseif (isset($item['unitPrice'])) {
                        $unitPrice = intval($item['unitPrice']);
                    } elseif (isset($item['product']['price'])) {
                        $unitPrice = intval($item['product']['price']);
                    }

                    $productSnapshot = $item['product_snapshot'] ?? $item['product'] ?? [];

                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => $productId,
                        'product_snapshot' => json_encode($productSnapshot),
                        'quantity' => isset($item['quantity']) ? intval($item['quantity']) : (isset($item['qty']) ? intval($item['qty']) : 1),
                        'unit_price' => $unitPrice ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
