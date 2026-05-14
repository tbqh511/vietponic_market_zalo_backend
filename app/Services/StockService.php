<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\ZaloOrder;
use App\Models\ZaloProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Check if all items in an order can be fulfilled.
     * Returns true if all available, or an array of shortage details.
     *
     * @param  array  $items  Each item: ['product_id' => int, 'quantity' => int]
     * @return true|array
     */
    public function checkAvailability(array $items): bool|array
    {
        $shortages = [];

        foreach ($items as $item) {
            $product = ZaloProduct::find($item['product_id']);
            if (!$product) {
                continue;
            }
            if (!$product->isAvailable((int) $item['quantity'])) {
                $shortages[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'requested'    => (int) $item['quantity'],
                    'available'    => $product->stock_available,
                ];
            }
        }

        return empty($shortages) ? true : $shortages;
    }

    /**
     * Reserve stock for all items when an order is placed.
     * Records 'reserved' movements.
     */
    public function reserveItems(int $orderId, array $items, ?int $adminId = null): void
    {
        DB::transaction(function () use ($orderId, $items, $adminId) {
            foreach ($items as $item) {
                $product = ZaloProduct::lockForUpdate()->find($item['product_id']);
                if (!$product) {
                    continue;
                }
                $qty = (int) $item['quantity'];
                $before = $product->stock;

                $product->reserveStock($qty);
                $product->refresh();

                StockMovement::create([
                    'product_id'      => $product->id,
                    'order_id'        => $orderId,
                    'movement_type'   => 'reserved',
                    'quantity_change' => $qty,
                    'quantity_before' => $before,
                    'quantity_after'  => $product->stock,
                    'note'            => "Đặt giữ cho đơn hàng #{$orderId}",
                    'created_by'      => $adminId,
                ]);
            }
        });
    }

    /**
     * Release reservation when an order is cancelled.
     * Records 'unreserved' movements.
     */
    public function releaseReservation(int $orderId, ?int $adminId = null): void
    {
        DB::transaction(function () use ($orderId, $adminId) {
            $order = ZaloOrder::with('items')->find($orderId);
            if (!$order) {
                return;
            }

            foreach ($order->items as $item) {
                if (!$item->product_id) {
                    continue;
                }
                $product = ZaloProduct::lockForUpdate()->find($item->product_id);
                if (!$product) {
                    continue;
                }
                $qty = (int) $item->quantity;
                $before = $product->stock;

                $product->releaseStock($qty);
                $product->refresh();

                StockMovement::create([
                    'product_id'      => $product->id,
                    'order_id'        => $orderId,
                    'movement_type'   => 'unreserved',
                    'quantity_change' => $qty,
                    'quantity_before' => $before,
                    'quantity_after'  => $product->stock,
                    'note'            => "Giải phóng giữ — đơn hàng #{$orderId} bị huỷ",
                    'created_by'      => $adminId,
                ]);
            }
        });
    }

    /**
     * Deduct actual stock after successful payment.
     * Records 'export' movements and clears reserved qty.
     */
    public function deductOnPayment(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = ZaloOrder::with('items')->find($orderId);
            if (!$order) {
                return;
            }

            foreach ($order->items as $item) {
                if (!$item->product_id) {
                    continue;
                }
                $product = ZaloProduct::lockForUpdate()->find($item->product_id);
                if (!$product) {
                    continue;
                }
                $qty = (int) $item->quantity;
                $before = $product->stock;

                $product->deductStock($qty);
                $product->refresh();

                StockMovement::create([
                    'product_id'      => $product->id,
                    'order_id'        => $orderId,
                    'movement_type'   => 'export',
                    'quantity_change' => -$qty,
                    'quantity_before' => $before,
                    'quantity_after'  => $product->stock,
                    'note'            => "Xuất kho — đơn hàng #{$orderId} thanh toán thành công",
                    'created_by'      => null,
                ]);
            }
        });
    }

    /**
     * Manually import stock (add quantity).
     */
    public function importStock(int $productId, int $qty, string $note, int $adminId): void
    {
        DB::transaction(function () use ($productId, $qty, $note, $adminId) {
            $product = ZaloProduct::lockForUpdate()->findOrFail($productId);
            $before = $product->stock;

            $product->increment('stock', $qty);
            $product->refresh();

            StockMovement::create([
                'product_id'      => $product->id,
                'order_id'        => null,
                'movement_type'   => 'import',
                'quantity_change' => $qty,
                'quantity_before' => $before,
                'quantity_after'  => $product->stock,
                'note'            => $note,
                'created_by'      => $adminId,
            ]);
        });
    }

    /**
     * Adjust stock to an exact quantity (manual correction).
     */
    public function adjustStock(int $productId, int $newQty, string $note, int $adminId): void
    {
        DB::transaction(function () use ($productId, $newQty, $note, $adminId) {
            $product = ZaloProduct::lockForUpdate()->findOrFail($productId);
            $before = $product->stock;
            $change = $newQty - $before;

            $product->stock = $newQty;
            $product->save();

            StockMovement::create([
                'product_id'      => $product->id,
                'order_id'        => null,
                'movement_type'   => 'adjustment',
                'quantity_change' => $change,
                'quantity_before' => $before,
                'quantity_after'  => $newQty,
                'note'            => $note,
                'created_by'      => $adminId,
            ]);
        });
    }

    /**
     * Update the reorder point threshold for a product.
     */
    public function updateReorderPoint(int $productId, int $reorderPoint, int $adminId): void
    {
        ZaloProduct::where('id', $productId)->update(['reorder_point' => $reorderPoint]);
    }

    /**
     * Get all products currently at or below their reorder point.
     */
    public function getLowStockProducts(): Collection
    {
        return ZaloProduct::with('category')->lowStock()->orderBy('stock')->get();
    }

    /**
     * Get paginated movement history for a product.
     */
    public function getMovementHistory(int $productId, int $perPage = 20)
    {
        return StockMovement::with('order', 'creator')
            ->byProduct($productId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get stock summary report for a date range.
     *
     * @return array{products: Collection, totals: array}
     */
    public function getReport(?string $from, ?string $to): array
    {
        $movements = StockMovement::with('product')
            ->byDateRange($from, $to)
            ->get();

        $byProduct = $movements->groupBy('product_id')->map(function ($group) {
            $product = $group->first()->product;
            $imports    = $group->where('movement_type', 'import')->sum('quantity_change');
            $exports    = $group->where('movement_type', 'export')->sum(fn ($m) => abs($m->quantity_change));
            $adjustments = $group->where('movement_type', 'adjustment')->sum('quantity_change');

            return [
                'product_id'   => $product?->id,
                'product_name' => $product?->name ?? '(Đã xoá)',
                'imports'      => $imports,
                'exports'      => $exports,
                'adjustments'  => $adjustments,
                'net_change'   => $imports - $exports + $adjustments,
            ];
        })->values();

        return [
            'products' => $byProduct,
            'totals'   => [
                'total_imports'  => $movements->where('movement_type', 'import')->sum('quantity_change'),
                'total_exports'  => $movements->where('movement_type', 'export')->sum(fn ($m) => abs($m->quantity_change)),
                'total_movements'=> $movements->count(),
            ],
        ];
    }
}
