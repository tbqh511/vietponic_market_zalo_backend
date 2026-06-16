<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin web inventory dashboard. Sau khi chuyển sang batch model, view Blade
 * cũ vẫn render được nhờ accessor `total_remaining` gắn động lên product
 * (xem decorateWithTotals()). View nên đọc `$product->total_remaining`;
 * trong giai đoạn chuyển tiếp, các view chưa cập nhật vẫn fallback `stock`
 * key trên array (ở các API controller) hoặc 0 (nếu đọc trực tiếp $product->stock).
 *
 * `stock_status` filter:
 *   out  — không còn batch active hoặc tổng = 0
 *   low  — total > 0 AND total <= LOW_STOCK_THRESHOLD
 *   ok   — total > LOW_STOCK_THRESHOLD
 */
class StockController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request)
    {
        $query = ZaloProduct::with(['category', 'unit']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter theo stock_status: cần join subquery aggregate batch.
        if ($request->filled('stock_status') && in_array($request->stock_status, ['out', 'low', 'ok'], true)) {
            $totals = $this->aggregateAll();
            $threshold = StockService::LOW_STOCK_THRESHOLD;

            $filteredIds = collect($totals)->filter(function ($total) use ($request, $threshold) {
                return match ($request->stock_status) {
                    'out' => $total <= 0,
                    'low' => $total > 0 && $total <= $threshold,
                    'ok'  => $total > $threshold,
                    default => true,
                };
            })->keys()->all();

            // SKU không có batch active = total 0 → cũng thuộc 'out'.
            if ($request->stock_status === 'out') {
                $allIds = ZaloProduct::pluck('id')->all();
                $missing = array_diff($allIds, array_keys($totals));
                $filteredIds = array_merge($filteredIds, $missing);
            }

            $query->whereIn('id', $filteredIds);
        }

        $products = $query->orderBy('name')->paginate(25)->withQueryString();
        $this->decorateWithTotals($products->getCollection());

        $categories = ZaloCategory::orderBy('id')->get();

        return view('admin.inventory.index', compact('products', 'categories'));
    }

    public function show(Request $request, ZaloProduct $inventory)
    {
        $inventory->loadMissing('category', 'unit');
        $this->decorateWithTotals(collect([$inventory]));
        $filters   = $this->movementFilters($request);
        $movements = $this->stockService->getMovementHistory($inventory->id, $filters, 20);
        $batches   = \App\Models\FarmStockBatch::where('product_id', $inventory->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->fefo()
            ->with('farm:id,name')
            ->get();
        return view('admin.inventory.show', compact('inventory', 'movements', 'filters', 'batches'));
    }

    /**
     * Partial AJAX cho bảng lịch sử nhập/xuất (search/filter real-time + paging).
     */
    public function movements(Request $request, ZaloProduct $inventory)
    {
        $filters   = $this->movementFilters($request);
        $movements = $this->stockService->getMovementHistory($inventory->id, $filters, 20);
        return view('admin.inventory._movements', compact('inventory', 'movements', 'filters'));
    }

    /**
     * Chuẩn hoá các tham số filter cho lịch sử nhập/xuất.
     */
    private function movementFilters(Request $request): array
    {
        return [
            'q'      => $request->input('q'),
            'type'   => in_array($request->input('type'), ['in', 'out'], true) ? $request->input('type') : null,
            'source' => array_key_exists($request->input('source'), \App\Models\StockMovement::SOURCE_LABELS) ? $request->input('source') : null,
            'from'   => $request->input('from'),
            'to'     => $request->input('to'),
        ];
    }

    public function importForm(ZaloProduct $inventory)
    {
        $this->decorateWithTotals(collect([$inventory]));
        return view('admin.inventory.import', compact('inventory'));
    }

    public function importStore(Request $request, ZaloProduct $inventory)
    {
        $request->validate([
            'quantity'    => 'required|numeric|min:0.01',
            'batch_date'  => 'nullable|date',
            'expire_date' => 'nullable|date',
            'note'        => 'nullable|string|max:500',
        ]);

        try {
            $this->stockService->importStock(
                $inventory->id,
                (float) $request->quantity,
                $request->note ?: 'Nhập kho',
                auth()->id() ?? 0,
                $request->expire_date ?: null,
                $request->batch_date ?: null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Nhập kho thất bại: ' . $e->getMessage())->withInput();
        }

        return redirect()->route('inventory.show', $inventory->id)
            ->with('success', 'Nhập kho thành công. Tồn kho đã được cập nhật.');
    }

    public function adjust(Request $request, ZaloProduct $inventory)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0',
            'note'     => 'required|string|max:500',
        ]);

        try {
            $this->stockService->adjustStock(
                $inventory->id,
                (float) $request->quantity,
                $request->note,
                auth()->id() ?? 0
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Điều chỉnh thất bại: ' . $e->getMessage())->withInput();
        }

        return redirect()->route('inventory.show', $inventory->id)
            ->with('success', 'Điều chỉnh tồn kho thành công.');
    }

    public function quickExport(Request $request, ZaloProduct $inventory)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'note'     => 'nullable|string|max:500',
        ]);

        $qty  = (float) $request->quantity;
        $note = $request->note ?: 'Xuất kho nhanh từ danh sách';

        // Tính tổng hiện tại → adjust xuống (current - qty).
        $current = $this->stockService->getProductTotalRemaining($inventory->id);
        $newQty  = max(0.0, $current - $qty);

        try {
            $this->stockService->adjustStock($inventory->id, $newQty, $note, auth()->id() ?? 0);
        } catch (\Throwable $e) {
            return back()->with('error', 'Xuất kho thất bại: ' . $e->getMessage());
        }

        return back()->with('success', "Đã xuất {$qty} đơn vị khỏi kho \"{$inventory->name}\".");
    }

    /**
     * Reorder point không còn tồn tại sau khi chuyển sang batch — schema đã drop.
     * Form vẫn còn trong view cũ, không break route nhưng flash warning.
     */
    public function reorderPoint(Request $request, ZaloProduct $inventory)
    {
        return redirect()->route('inventory.show', $inventory->id)
            ->with('warning', 'Tính năng ngưỡng cảnh báo per-product đã bị thay bằng ngưỡng cứng = ' . StockService::LOW_STOCK_THRESHOLD . '. Liên hệ dev để mở lại cấu hình per-product nếu cần.');
    }

    public function lowStock()
    {
        $products = $this->stockService->getLowStockProducts();
        return view('admin.inventory.low_stock', compact('products'));
    }

    public function report(Request $request)
    {
        $from   = $request->input('from');
        $to     = $request->input('to');
        $report = $this->stockService->getReport($from, $to);

        return view('admin.inventory.report', compact('report', 'from', 'to'));
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Gắn `total_remaining` (float) và `stock` (alias = total_remaining)
     * lên từng product để view Blade cũ render được mà không phải đổi tên field.
     * Gọi sau khi query xong, trên collection nhỏ (1 page = 25 row).
     */
    private function decorateWithTotals($collection): void
    {
        if ($collection->isEmpty()) return;

        $ids = $collection->pluck('id')->all();
        $totals = DB::table('farm_stock_batches')
            ->whereIn('product_id', $ids)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_remaining) AS total')
            ->pluck('total', 'product_id');

        foreach ($collection as $product) {
            $total = (float) ($totals[$product->id] ?? 0);
            $product->setAttribute('total_remaining', $total);
            // Alias để view cũ dùng $product->stock vẫn ra số đúng.
            $product->setAttribute('stock', $total);
            $product->setAttribute('stock_reserved', 0);
            $product->setAttribute('reorder_point', StockService::LOW_STOCK_THRESHOLD);
        }
    }

    private function aggregateAll(): array
    {
        return DB::table('farm_stock_batches')
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_remaining) AS total')
            ->pluck('total', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
