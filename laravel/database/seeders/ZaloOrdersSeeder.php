<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloDelivery;

class ZaloOrdersSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('mock/orders.json');
        if (!File::exists($path)) {
            $this->command->warn('mock/orders.json not found');
            return;
        }
        $json = json_decode(File::get($path), true);
        foreach ($json as $order) {
            // create order
            $orderModel = ZaloOrder::updateOrCreate(['id' => $order['id']], [
                'status' => $order['status'] ?? null,
                'payment_status' => $order['paymentStatus'] ?? null,
                'created_at' => isset($order['createdAt']) ? date('Y-m-d H:i:s', strtotime($order['createdAt'])) : null,
                'received_at' => isset($order['receivedAt']) ? date('Y-m-d H:i:s', strtotime($order['receivedAt'])) : null,
                'total' => $order['total'] ?? 0,
                'note' => $order['note'] ?? null,
                'customer_id' => 1,
            ]);

            // items
            if (!empty($order['items'])) {
                foreach ($order['items'] as $item) {
                    $product = $item['product'] ?? null;
                    ZaloOrderItem::create([
                        'order_id' => $orderModel->id,
                        'product_id' => $product['id'] ?? null,
                        'name' => $product['name'] ?? null,
                        'price' => $product['price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                        'image' => $product['image'] ?? null,
                        'detail' => $product['detail'] ?? null,
                    ]);
                }
            }

            // delivery
            if (!empty($order['delivery'])) {
                $d = $order['delivery'];
                $deliveryData = [
                    'order_id' => $orderModel->id,
                    'type' => $d['type'] ?? null,
                    'alias' => $d['alias'] ?? null,
                    'address' => $d['address'] ?? null,
                    'name' => $d['name'] ?? null,
                    'phone' => $d['phone'] ?? null,
                    'station_id' => $d['id'] ?? null,
                    'station_name' => $d['name'] ?? null,
                    'station_image' => $d['image'] ?? null,
                    'lat' => data_get($d, 'location.lat'),
                    'lng' => data_get($d, 'location.lng'),
                ];
                ZaloDelivery::updateOrCreate(['order_id' => $orderModel->id], $deliveryData);
            }
        }
    }
}
