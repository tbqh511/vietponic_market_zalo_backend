<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloCategory;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use App\Services\StockService;

/**
 * Dựng 1 đơn ĐÃ trừ kho (FEFO reserve) để test release/auto-cancel:
 * farm + batch active + product + order + order_item + reserveItems().
 *
 * Dùng chung cho UpdateOrderStatusTest và CancelUnpaidOrderTest.
 */
trait BuildsReservedOrderFactory
{
    /**
     * @return array{0: ZaloOrder, 1: FarmStockBatch, 2: ZaloProduct}
     */
    protected function makeReservedOrder(array $orderOverrides = [], float $orderQty = 4, float $batchQty = 10): array
    {
        static $seq = 0;
        $seq++;

        ZaloCategory::firstOrCreate(['id' => 1], ['name' => 'Test Category']);

        $owner = $this->makeReservedCustomer("owner{$seq}");
        $farm = Farm::create([
            'code'              => 'RSV' . uniqid(),
            'name'              => "Reserved Farm {$seq}",
            'owner_customer_id' => $owner->id,
            'address'           => 'Đà Lạt',
            'commission_rate'   => 0.1000,
            'payment_cycle'     => 'monthly',
            'is_active'         => true,
            'approved_at'       => now(),
        ]);

        $product = ZaloProduct::create([
            'id'          => 9100 + $seq,
            'category_id' => 1,
            'name'        => "Rau Reserved {$seq}",
            'price'       => '15000',
        ]);

        $batch = FarmStockBatch::create([
            'farm_id'            => $farm->id,
            'product_id'         => $product->id,
            'batch_date'         => now()->toDateString(),
            'quantity_in'        => $batchQty,
            'quantity_sold'      => 0,
            'quantity_remaining' => $batchQty, // SQLite fallback
            'cost_price'         => 10000,
            'expire_date'        => now()->addDays(10)->toDateString(),
            'status'             => 'active',
        ]);

        $buyer = $this->makeReservedCustomer("buyer{$seq}");
        $order = ZaloOrder::create(array_merge([
            'customer_id'    => $buyer->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'BANK_SANDBOX',
            'total'          => (string) (15000 * $orderQty),
            'created_at'     => now(),
        ], $orderOverrides));

        ZaloOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'name'       => $product->name,
            'price'      => '15000',
            'quantity'   => $orderQty,
        ]);

        app(StockService::class)->reserveItems($order->id, [
            ['product_id' => $product->id, 'quantity' => $orderQty],
        ]);

        return [$order, $batch->fresh(), $product];
    }

    private function makeReservedCustomer(string $tag): Customer
    {
        return Customer::create([
            'name'        => "Cust {$tag}",
            'firebase_id' => '',
            'email'       => "{$tag}_" . uniqid() . '@test.local',
            'mobile'      => '09' . substr((string) (crc32($tag . uniqid())), 0, 8),
            'address'     => '',
            'logintype'   => 'mobile',
            'isActive'    => 1,
        ]);
    }
}
