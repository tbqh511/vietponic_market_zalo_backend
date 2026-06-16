<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\StockMovement;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * StockService — Farm Partner Hub edition.
 *
 * Tồn kho KHÔNG còn ở cột zalo_products.stock. Mọi check/reserve/deduct giờ
 * dựa trên SUM(farm_stock_batches.quantity_remaining WHERE status='active').
 *
 * FEFO allocation timing (đã chốt với user):
 *   - reserveItems()       = phân bổ FEFO ngay khi tạo order. Tăng
 *                            batch.quantity_sold + split zalo_order_items
 *                            theo từng batch, gắn farm_id / cost_price_snapshot.
 *   - deductOnPayment()    = NO-OP (stock đã trừ ở reserve). Giữ method để
 *                            listener không phải đổi signature.
 *   - releaseReservation() = giảm batch.quantity_sold dựa vào farm_stock_batch_id
 *                            đã ghi sẵn trên order_items.
 *
 * Lưu ý quan trọng cho caller cũ:
 *   - checkAvailability() vẫn nhận shape [{product_id, quantity}] và trả
 *     true|array shortages — KHÔNG đổi signature, FE checkout không cần sửa.
 *   - import/export/adjust dùng farm_id. Admin web không gắn farm → bắt buộc
 *     phải truyền farmId. Method admin có wrapper riêng nhận default farm.
 *   - getLowStockProducts / getReport bám trên aggregate batch, không có
 *     "reorder_point" trên SKU nữa (đã drop). Ngưỡng cứng 5 tạm thời.
 */
class StockService
{
    /** Ngưỡng low-stock mặc định khi không có config per-product. */
    public const LOW_STOCK_THRESHOLD = 5;

    // ─── Read: check availability ─────────────────────────────────────────

    /**
     * Kiểm tra mọi item trong order có đủ tồn (aggregate batch active).
     *
     * @param  array  $items  Mỗi item: ['product_id' => int, 'quantity' => int|float]
     * @return true|array  true nếu đủ; ngược lại array shortages
     */
    public function checkAvailability(array $items): bool|array
    {
        $shortages = [];

        // Aggregate sẵn để không N+1 nếu order nhiều item.
        $productIds = collect($items)->pluck('product_id')->unique()->values()->all();
        if (empty($productIds)) {
            return true;
        }

        $available = DB::table('farm_stock_batches')
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_remaining) AS total')
            ->pluck('total', 'product_id');

        $products = ZaloProduct::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $qty = (float) $item['quantity'];

            $totalRemaining = (float) ($available[$pid] ?? 0);
            if ($totalRemaining + 1e-6 < $qty) {
                $shortages[] = [
                    'product_id'   => $pid,
                    'product_name' => $products->get($pid)?->name ?? '(Đã xoá)',
                    'requested'    => $qty,
                    'available'    => $totalRemaining,
                ];
            }
        }

        return empty($shortages) ? true : $shortages;
    }

    // ─── Core: FEFO allocation at order creation ──────────────────────────

    /**
     * Phân bổ FEFO + ghi nguồn gốc batch lên zalo_order_items.
     *
     * Flow:
     *   1. Cho mỗi item, query các batch active theo FEFO order.
     *   2. Trừ dần qty cần phân bổ vào từng batch (lockForUpdate).
     *   3. Tìm row zalo_order_items tương ứng (chưa có batch_id) và:
     *      - Nếu batch đầu đủ qty → update row hiện có với farm/batch/snapshot.
     *      - Nếu phải split → giữ row gốc cho batch đầu, INSERT thêm row
     *        cho batch tiếp theo (clone field cơ bản).
     *   4. Tăng batch.quantity_sold cho từng batch. quantity_remaining
     *      tự cập nhật vì là generated column.
     *
     * Tham số $items giữ shape cũ [{product_id, quantity}] để controller
     * không phải đổi. $adminId chỉ để compatibility (không lưu nữa — log đủ ở
     * batch.note nếu cần).
     *
     * Throws RuntimeException nếu giữa transaction phát hiện không đủ stock
     * (race với customer khác đặt cùng lúc) — caller cần bắt + rollback.
     */
    public function reserveItems(int $orderId, array $items, ?int $adminId = null): void
    {
        DB::transaction(function () use ($orderId, $items) {
            foreach ($items as $item) {
                $this->allocateOneItem($orderId, (int) $item['product_id'], (float) $item['quantity']);
            }
        });
    }

    /**
     * Phân bổ FEFO 1 item. Tách riêng để dễ test + reuse.
     */
    private function allocateOneItem(int $orderId, int $productId, float $qtyNeeded): void
    {
        if ($qtyNeeded <= 0) {
            return;
        }

        // Lock các batch active của product theo FEFO. Quan trọng: lock để
        // 2 transaction concurrent không cùng trừ 1 batch xuống âm.
        $batches = FarmStockBatch::query()
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->fefo()
            ->lockForUpdate()
            ->get();

        // Tìm row order_item tương ứng — phải có (controller đã insert trước
        // khi gọi reserveItems). Lấy row chưa có batch_id để phân bổ.
        $baseItem = ZaloOrderItem::where('order_id', $orderId)
            ->where('product_id', $productId)
            ->whereNull('farm_stock_batch_id')
            ->orderBy('id', 'asc')
            ->first();

        if (!$baseItem) {
            throw new RuntimeException(
                "Order item not found for allocation: order={$orderId}, product={$productId}"
            );
        }

        $remainingToAllocate = $qtyNeeded;
        $isFirstSlice        = true;

        foreach ($batches as $batch) {
            if ($remainingToAllocate <= 1e-6) {
                break;
            }

            $batchAvail = (float) $batch->quantity_remaining;
            $take = min($batchAvail, $remainingToAllocate);
            if ($take <= 0) {
                continue;
            }

            // Tăng quantity_sold; quantity_remaining tự update (generated col trên MySQL).
            // Trên SQLite (test env), generated col không hỗ trợ — cập nhật thủ công.
            $newQtySold = (float) $batch->quantity_sold + $take;
            $batch->quantity_sold = $newQtySold;
            $batch->save();

            $batch->refresh();

            // SQLite fallback: quantity_remaining không được DB tính tự động.
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
                $batch->quantity_remaining = (float) $batch->quantity_in - $newQtySold;
                $batch->saveQuietly();
            }

            // Mark batch depleted nếu chạm hết. Tránh batch còn
            // status='active' với quantity_remaining=0 đẩy index FEFO query nặng.
            if ((float) $batch->quantity_remaining <= 1e-6) {
                $batch->status = 'depleted';
                $batch->save();
            }

            // Ghi ledger xuất kho cho slice này (1 batch = 1 dòng movement).
            $this->recordMovement(
                productId: $productId,
                type: 'out',
                source: 'order',
                qty: $take,
                batchId: $batch->id,
                orderId: $orderId,
                farmId: $batch->farm_id,
            );

            if ($isFirstSlice) {
                // Slice đầu: update row order_item gốc.
                $baseItem->farm_stock_batch_id  = $batch->id;
                $baseItem->farm_id              = $batch->farm_id;
                $baseItem->cost_price_snapshot  = $batch->cost_price;
                $baseItem->quantity             = $take;
                $baseItem->system_total         = $take * (float) ($baseItem->conversion_factor ?? 1);
                $baseItem->save();
                $isFirstSlice = false;
            } else {
                // Slice tiếp theo: clone row gốc thành row mới gắn batch khác.
                ZaloOrderItem::create([
                    'order_id'            => $orderId,
                    'product_id'          => $productId,
                    'farm_stock_batch_id' => $batch->id,
                    'farm_id'             => $batch->farm_id,
                    'cost_price_snapshot' => $batch->cost_price,
                    'name'                => $baseItem->name,
                    'price'               => $baseItem->price,
                    'quantity'            => $take,
                    'image'               => $baseItem->image,
                    'detail'              => $baseItem->detail,
                    'unit_label'          => $baseItem->unit_label,
                    'system_unit'         => $baseItem->system_unit,
                    'conversion_factor'   => $baseItem->conversion_factor,
                    'system_total'        => $take * (float) ($baseItem->conversion_factor ?? 1),
                ]);
            }

            $remainingToAllocate -= $take;
        }

        if ($remainingToAllocate > 1e-6) {
            // Race condition: stock đã check ở checkAvailability nhưng giữa lúc
            // đó có order khác lấy hết. Rollback transaction.
            throw new RuntimeException(
                "Insufficient stock during allocation: product={$productId}, " .
                "missing=" . number_format($remainingToAllocate, 2)
            );
        }
    }

    /**
     * Huỷ phân bổ khi đơn bị huỷ. Quét order_items, hoàn lại quantity_sold
     * cho từng batch, đồng thời reset batch.status về 'active' nếu trước đó
     * đã bị mark depleted.
     *
     * Không xoá row order_item — giữ làm audit. Chỉ clear batch_id/farm_id
     * sẽ làm dashboard farm thấy đơn cancelled lẫn vào → để nguyên, FE đã
     * lọc theo zalo_orders.status.
     */
    public function releaseReservation(int $orderId, ?int $adminId = null): void
    {
        DB::transaction(function () use ($orderId) {
            $items = ZaloOrderItem::where('order_id', $orderId)
                ->whereNotNull('farm_stock_batch_id')
                ->get();

            foreach ($items as $item) {
                $batch = FarmStockBatch::where('id', $item->farm_stock_batch_id)
                    ->lockForUpdate()
                    ->first();
                if (!$batch) {
                    // Batch đã bị xoá (FK set null đã clear) — bỏ qua.
                    continue;
                }

                $newQtySold = max(0, (float) $batch->quantity_sold - (float) $item->quantity);
                $batch->quantity_sold = $newQtySold;
                // Nếu trước đó batch bị mark depleted vì hết hàng, giờ có lại
                // → revert sang active để bán tiếp.
                if ($batch->status === 'depleted') {
                    $batch->status = 'active';
                }
                $batch->save();

                // SQLite fallback cho generated column.
                if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
                    $batch->quantity_remaining = (float) $batch->quantity_in - $newQtySold;
                    $batch->saveQuietly();
                }

                // Ghi ledger hoàn kho (nhập lại) khi huỷ đơn.
                $this->recordMovement(
                    productId: (int) $item->product_id,
                    type: 'in',
                    source: 'order_cancel',
                    qty: (float) $item->quantity,
                    batchId: (int) $batch->id,
                    orderId: $orderId,
                    farmId: $batch->farm_id,
                );
            }
        });
    }

    /**
     * No-op trong batch model — stock đã trừ ở reserveItems.
     * Giữ method để DeductStockOnPayment listener không phải đổi.
     */
    public function deductOnPayment(int $orderId): void
    {
        // intentionally no-op; stock đã giảm khi tạo order (FEFO allocation).
    }

    // ─── Admin / Farm: import / adjust / export ───────────────────────────

    /**
     * Admin import: tạo batch mới gắn vào 1 farm. Vì admin web hiện không có
     * khái niệm "farm nào nhập", method này yêu cầu caller truyền farmId rõ.
     * Trường hợp admin nhập kho tổng (chưa gán farm cụ thể), seeder/admin
     * phải tạo trước 1 farm "main" và truyền id đó.
     *
     * Note: phương thức cũ importStock(productId, qty, note, adminId) GIỮ
     * signature để StockController/StockApiController không crash khi build,
     * nhưng sẽ throw nếu không có farm mặc định.
     */
    public function importStock(int $productId, int|float $qty, string $note, int $adminId): void
    {
        $defaultFarm = $this->resolveDefaultFarm();
        if (!$defaultFarm) {
            throw new RuntimeException(
                'Không có farm nào active. Admin phải tạo farm trước khi import qua màn này.'
            );
        }

        $this->createBatch(
            farmId: $defaultFarm->id,
            productId: $productId,
            qty: (float) $qty,
            costPrice: $this->resolveCostPrice($defaultFarm->id, $productId),
            note: $note,
            source: 'admin_import',
            createdBy: $adminId,
        );
    }

    /**
     * Farm partner import: tạo batch mới gắn farm của customer đang đăng nhập.
     * costPrice mặc định lấy từ pivot farm_product.cost_price, hoặc 0 nếu chưa set.
     *
     * Giữ signature cũ. $farmCustomerId → resolve farm qua relation owner.
     */
    public function importStockByFarm(int $productId, int|float $qty, string $note, int $farmCustomerId): void
    {
        $farm = Farm::active()->where('owner_customer_id', $farmCustomerId)->first();
        if (!$farm) {
            throw new RuntimeException("Customer #{$farmCustomerId} không sở hữu farm active nào.");
        }

        $this->createBatch(
            farmId: $farm->id,
            productId: $productId,
            qty: (float) $qty,
            costPrice: $this->resolveCostPrice($farm->id, $productId),
            note: $note,
            source: 'farm_import',
            createdBy: $farmCustomerId,
        );
    }

    /**
     * Farm export thủ công (huỷ batch hoặc bớt qty). Vì user chọn batch-view,
     * caller mới (FarmStockController.export) sẽ truyền batchId trực tiếp qua
     * phương thức adjustBatchQuantity bên dưới. Method này GIỮ signature cũ
     * (product-level) cho compatibility — trừ FEFO trên các batch của farm.
     */
    public function exportStock(int $productId, int|float $qty, string $note, int $farmCustomerId, string $source = 'farm_export', ?int $createdBy = null): void
    {
        $farm = Farm::active()->where('owner_customer_id', $farmCustomerId)->first();
        if (!$farm) {
            throw new RuntimeException("Customer #{$farmCustomerId} không sở hữu farm active nào.");
        }

        $remaining = (float) $qty;
        $createdBy = $createdBy ?? $farmCustomerId;

        DB::transaction(function () use ($farm, $productId, &$remaining, $note, $source, $createdBy) {
            $batches = FarmStockBatch::query()
                ->where('farm_id', $farm->id)
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->where('quantity_remaining', '>', 0)
                ->fefo()
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 1e-6) break;

                $take = min((float) $batch->quantity_remaining, $remaining);
                $batch->quantity_sold = (float) $batch->quantity_sold + $take;
                $batch->note = trim(($batch->note ? $batch->note . "\n" : '') . "[export] {$note}");
                $batch->save();

                $batch->refresh();
                if ((float) $batch->quantity_remaining <= 1e-6) {
                    $batch->status = 'depleted';
                    $batch->save();
                }

                // Ghi ledger xuất kho thủ công (admin điều chỉnh / farm export).
                $this->recordMovement(
                    productId: $productId,
                    type: 'out',
                    source: $source,
                    qty: $take,
                    batchId: (int) $batch->id,
                    farmId: $farm->id,
                    note: $note,
                    createdBy: $createdBy,
                );

                $remaining -= $take;
            }
        });

        if ($remaining > 1e-6) {
            throw new RuntimeException(
                "Không đủ tồn kho để export: product={$productId}, thiếu " . number_format($remaining, 2)
            );
        }
    }

    /**
     * Admin "điều chỉnh số lượng tuyệt đối" — không khớp tốt với mô hình batch
     * (vì batch là lô riêng). Implementation: hợp lý nhất là tạo batch
     * "adjustment" với qty (newQty - currentTotal), có thể âm.
     *
     * Vì DB không cho phép quantity_in âm (decimal unsigned mặc định không
     * dùng nhưng business logic nên giữ >= 0), nếu chênh lệch âm → export
     * FEFO; chênh lệch dương → tạo batch mới.
     */
    public function adjustStock(int $productId, int|float $newQty, string $note, int $adminId): void
    {
        $currentTotal = $this->getProductTotalRemaining($productId);
        $diff = (float) $newQty - $currentTotal;

        if (abs($diff) < 1e-6) {
            return;
        }

        $defaultFarm = $this->resolveDefaultFarm();
        if (!$defaultFarm) {
            throw new RuntimeException('Không có farm active — không thể adjust.');
        }

        if ($diff > 0) {
            $this->createBatch(
                farmId: $defaultFarm->id,
                productId: $productId,
                qty: $diff,
                costPrice: $this->resolveCostPrice($defaultFarm->id, $productId),
                note: "[adjust] {$note}",
                source: 'admin_adjust',
                createdBy: $adminId,
            );
        } else {
            // diff < 0: trừ |diff| theo FEFO trên tất cả batch của default farm.
            $this->exportStock($productId, abs($diff), "[adjust] {$note}", $defaultFarm->owner_customer_id ?? 0, 'admin_adjust', $adminId);
        }
    }

    /**
     * Reorder point đã bị drop khỏi schema. Method giữ để controller cũ
     * không crash — log warning + no-op.
     */
    public function updateReorderPoint(int $productId, int $reorderPoint, int $adminId): void
    {
        \Log::warning('StockService::updateReorderPoint called but reorder_point column was dropped.', [
            'product_id'    => $productId,
            'reorder_point' => $reorderPoint,
        ]);
    }

    // ─── Reports / queries ────────────────────────────────────────────────

    /**
     * SKU dưới ngưỡng cảnh báo. Vì không còn reorder_point per-product,
     * dùng ngưỡng cứng LOW_STOCK_THRESHOLD. Trả về Collection<ZaloProduct>
     * có thêm attribute động total_remaining để view dùng được.
     */
    public function getLowStockProducts(): Collection
    {
        $rows = DB::table('farm_stock_batches')
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_remaining) AS total')
            ->havingRaw('SUM(quantity_remaining) <= ?', [self::LOW_STOCK_THRESHOLD])
            ->get()
            ->keyBy('product_id');

        $allProductIds = ZaloProduct::pluck('id');
        // Product không có batch active = total 0 → cũng low.
        foreach ($allProductIds as $pid) {
            if (!$rows->has($pid)) {
                $rows->put($pid, (object) ['product_id' => $pid, 'total' => 0]);
            }
        }

        $productIds = $rows->keys()->all();
        $products = ZaloProduct::with('category')
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get();

        // Gắn total_remaining để view không phải re-query.
        $products->each(function ($p) use ($rows) {
            $p->setAttribute('total_remaining', (float) ($rows[$p->id]->total ?? 0));
        });

        return $products;
    }

    /**
     * Lịch sử nhập/xuất của 1 product — đọc từ ledger stock_movements.
     * Hỗ trợ filter: q (tìm theo note / #order / #batch), type (in|out),
     * source, from/to (theo ngày). Trả LengthAwarePaginator + giữ query string.
     *
     * @param  array  $filters  ['q','type','source','from','to']
     */
    public function getMovementHistory(int $productId, array $filters = [], int $perPage = 20)
    {
        return StockMovement::query()
            ->with(['batch:id,batch_date', 'order:id,status,created_at'])
            ->where('product_id', $productId)
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->when($filters['q'] ?? null, function ($q, $kw) {
                $kw = trim($kw);
                $num = ltrim($kw, '#');
                $q->where(function ($w) use ($kw, $num) {
                    $w->where('note', 'like', "%{$kw}%");
                    if ($num !== '' && ctype_digit($num)) {
                        $w->orWhere('order_id', $num)
                          ->orWhere('batch_id', $num);
                    }
                });
            })
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            // Pin path tới endpoint AJAX để link phân trang luôn trả partial,
            // dù render lần đầu từ route inventory.show.
            ->withPath(route('inventory.movements', $productId));
    }

    /**
     * Báo cáo theo khoảng thời gian. Imports = sum quantity_in của batch
     * (theo batch_date), exports = sum quantity_sold thuộc order_items
     * trong range. Schema đã đổi nên kết quả không 1:1 với báo cáo cũ —
     * caller view phải cập nhật, nhưng giữ keys để không crash.
     */
    public function getReport(?string $from, ?string $to): array
    {
        $importsQ = DB::table('farm_stock_batches')
            ->selectRaw('product_id, SUM(quantity_in) AS imports');
        $exportsQ = DB::table('zalo_order_items')
            ->join('zalo_orders', 'zalo_orders.id', '=', 'zalo_order_items.order_id')
            ->selectRaw('zalo_order_items.product_id AS product_id, SUM(zalo_order_items.quantity) AS exports')
            ->where('zalo_orders.status', 'delivered');

        if ($from) {
            $importsQ->where('batch_date', '>=', $from);
            $exportsQ->where('zalo_orders.delivered_at', '>=', $from);
        }
        if ($to) {
            $importsQ->where('batch_date', '<=', $to);
            $exportsQ->where('zalo_orders.delivered_at', '<=', $to . ' 23:59:59');
        }

        $imports = $importsQ->groupBy('product_id')->pluck('imports', 'product_id');
        $exports = $exportsQ->groupBy('zalo_order_items.product_id')->pluck('exports', 'product_id');

        $productIds = $imports->keys()->merge($exports->keys())->unique();
        $products = ZaloProduct::with('unit')->whereIn('id', $productIds)->get()->keyBy('id');

        $byProduct = $productIds->map(function ($pid) use ($imports, $exports, $products) {
            $p = $products->get($pid);
            $imp = (float) ($imports[$pid] ?? 0);
            $exp = (float) ($exports[$pid] ?? 0);
            $factor = (float) ($p?->conversion_factor ?? 1);
            $sysUnit = $p?->system_unit ?? 'piece';

            return [
                'product_id'         => $pid,
                'product_name'       => $p?->name ?? '(Đã xoá)',
                'unit_label'         => $p?->unit?->label,
                'system_unit'        => $sysUnit,
                'conversion_factor'  => $factor,
                'imports'            => $imp,
                'exports'            => $exp,
                'adjustments'        => 0.0,
                'net_change'         => $imp - $exp,
                'imports_system'     => $imp * $factor,
                'exports_system'     => $exp * $factor,
                'adjustments_system' => 0.0,
                'net_change_system'  => ($imp - $exp) * $factor,
            ];
        })->values();

        $systemTotals = [];
        foreach ($byProduct as $row) {
            $unit = $row['system_unit'];
            if (!isset($systemTotals[$unit])) {
                $systemTotals[$unit] = [
                    'system_unit' => $unit,
                    'imports'     => 0.0,
                    'exports'     => 0.0,
                    'adjustments' => 0.0,
                    'net_change'  => 0.0,
                ];
            }
            $systemTotals[$unit]['imports']    += $row['imports_system'];
            $systemTotals[$unit]['exports']    += $row['exports_system'];
            $systemTotals[$unit]['net_change'] += $row['net_change_system'];
        }

        return [
            'products'      => $byProduct,
            'totals'        => [
                'total_imports'   => (float) $imports->sum(),
                'total_exports'   => (float) $exports->sum(),
                'total_movements' => $byProduct->count(),
            ],
            'system_totals' => array_values($systemTotals),
        ];
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Tổng tồn của 1 SKU (sum batch active).
     */
    public function getProductTotalRemaining(int $productId): float
    {
        return (float) DB::table('farm_stock_batches')
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->sum('quantity_remaining');
    }

    /**
     * Farm mặc định cho admin web (chưa hỗ trợ chọn farm).
     *
     * Ưu tiên farm được gắn làm "Hub đóng gói" (is_packing_hub = true) có id
     * nhỏ nhất — đây là nơi gom tồn kho admin nhập tay. Nếu chưa có hub nào
     * được gắn thì fallback về farm active đầu tiên theo id để không vỡ luồng
     * nhập/adjust cũ.
     */
    private function resolveDefaultFarm(): ?Farm
    {
        return Farm::packingHub()->orderBy('id')->first()
            ?? Farm::active()->orderBy('id')->first();
    }

    /**
     * cost_price mặc định cho cặp (farm, product) — đọc từ pivot farm_product.
     */
    private function resolveCostPrice(int $farmId, int $productId): float
    {
        $row = DB::table('farm_product')
            ->where('farm_id', $farmId)
            ->where('product_id', $productId)
            ->first();
        return (float) ($row->cost_price ?? 0);
    }

    /**
     * Tạo 1 batch mới — dùng chung cho import (admin/farm) và adjust.
     */
    private function createBatch(int $farmId, int $productId, float $qty, float $costPrice, string $note, string $source = 'admin_import', ?int $createdBy = null): FarmStockBatch
    {
        $batch = FarmStockBatch::create([
            'farm_id'       => $farmId,
            'product_id'    => $productId,
            'batch_date'    => now()->toDateString(),
            'quantity_in'   => $qty,
            'quantity_sold' => 0,
            'cost_price'    => $costPrice,
            'expire_date'   => null,
            'status'        => 'active',
            'note'          => $note,
        ]);

        $this->recordMovement(
            productId: $productId,
            type: 'in',
            source: $source,
            qty: $qty,
            batchId: (int) $batch->id,
            farmId: $farmId,
            note: $note,
            createdBy: $createdBy,
        );

        return $batch;
    }

    // ─── Ledger (stock_movements) ─────────────────────────────────────────

    /**
     * Ghi 1 dòng ledger nhập/xuất. quantity được ký dấu theo $type:
     * in → +abs, out → -abs (để SUM(quantity) per product khớp tồn).
     */
    private function recordMovement(
        int $productId,
        string $type,
        string $source,
        float $qty,
        ?int $batchId = null,
        ?int $orderId = null,
        ?int $farmId = null,
        ?string $note = null,
        ?int $createdBy = null,
    ): void {
        $signed = $type === 'out' ? -abs($qty) : abs($qty);

        StockMovement::create([
            'product_id' => $productId,
            'batch_id'   => $batchId,
            'order_id'   => $orderId,
            'farm_id'    => $farmId,
            'type'       => $type,
            'source'     => $source,
            'quantity'   => $signed,
            'note'       => $note !== null ? mb_substr($note, 0, 500) : null,
            'created_by' => ($createdBy !== null && $createdBy > 0) ? $createdBy : null,
        ]);
    }

    /**
     * Ghi ledger nhập kho cho 1 batch được tạo TRỰC TIẾP ngoài createBatch()
     * (vd FarmStockController tự gọi FarmStockBatch::create). Public để controller
     * farm hub dùng lại sau khi tạo batch.
     */
    public function recordBatchIn(FarmStockBatch $batch, string $source = 'farm_import', ?int $createdBy = null): void
    {
        $this->recordMovement(
            productId: (int) $batch->product_id,
            type: 'in',
            source: $source,
            qty: (float) $batch->quantity_in,
            batchId: (int) $batch->id,
            farmId: (int) $batch->farm_id,
            note: $batch->note,
            createdBy: $createdBy,
        );
    }

    /**
     * Ghi ledger xuất kho khi đóng/thu hồi 1 batch còn tồn (status → recalled/expired).
     * Ghi -quantity_remaining để phản ánh phần tồn bị rút khỏi khả dụng.
     */
    public function recordBatchClose(FarmStockBatch $batch, ?string $note = null, ?int $createdBy = null): void
    {
        $remaining = (float) $batch->quantity_remaining;
        if ($remaining <= 1e-6) {
            return;
        }

        $this->recordMovement(
            productId: (int) $batch->product_id,
            type: 'out',
            source: 'batch_close',
            qty: $remaining,
            batchId: (int) $batch->id,
            farmId: (int) $batch->farm_id,
            note: $note,
            createdBy: $createdBy,
        );
    }
}
