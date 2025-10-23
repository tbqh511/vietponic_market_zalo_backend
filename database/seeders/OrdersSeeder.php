<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrdersSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/orders.json');
        if (! file_exists($path)) return;

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) return;

        DB::table('order_items')->truncate();
        DB::table('deliveries')->truncate();
        DB::table('orders')->truncate();

        foreach ($data as $order) {
            $orderId = $order['id'] ?? null;
            DB::table('orders')->insert([
                'id' => $orderId,
                'status' => $order['status'] ?? 'new',
                'payment_status' => $order['payment_status'] ?? 'unpaid',
                'created_at' => isset($order['created_at']) ? $order['created_at'] : now(),
                'received_at' => $order['received_at'] ?? null,
                'total' => isset($order['total']) ? intval($order['total']) : 0,
                'note' => $order['note'] ?? null,
                'delivery_id' => null,
                'created_by_user_id' => $order['created_by_user_id'] ?? null,
                'updated_at' => $order['updated_at'] ?? null,
            ]);

            // delivery
            if (! empty($order['delivery'])) {
                $delivery = $order['delivery'];
                $deliveryId = $delivery['id'] ?? null;
                DB::table('deliveries')->insert([
                    'id' => $deliveryId,
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
                ]);

                // update order.delivery_id
                DB::table('orders')->where('id', $orderId)->update(['delivery_id' => $deliveryId]);
            }

            // items
            if (! empty($order['items']) && is_array($order['items'])) {
                foreach ($order['items'] as $item) {
                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'] ?? null,
                        'product_snapshot' => json_encode($item['product_snapshot'] ?? $item['product'] ?? []),
                        'quantity' => isset($item['quantity']) ? intval($item['quantity']) : 1,
                        'unit_price' => isset($item['unit_price']) ? intval($item['unit_price']) : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
