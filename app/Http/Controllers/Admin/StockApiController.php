<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin Inventory API — sau khi chuyển sang batch model.
 *
 * Mỗi SKU không còn cột `stock` riêng. Tổng tồn = SUM(batch.quantity_remaining)
 * trên các batch active. Controller giữ shape response cũ (key `stock`,
 * `stock_available`) để FE admin (BDS) chưa cập nhật vẫn render được —
 * nhưng giá trị là aggregate qua batch.
 */
class StockApiController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(): JsonResponse
    {
        $products = ZaloProduct::with('category')->orderBy('name')->get();
        $totals = $this->aggregateByProduct($products->pluck('id')->all());

        $data = $products->map(function ($p) use ($totals) {
            $total = (float) ($totals[$p->id] ?? 0);
            return [
                'id'              => (int) $p->id,
                'name'            => $p->name,
                'category'        => $p->category?->name,
                // Giá trị aggregate. Giữ key cũ để FE BDS không phải đổi.
                'stock'           => $total,
                'stock_reserved'  => 0,
                'stock_available' => $total,
                'reorder_point'   => StockService::LOW_STOCK_THRESHOLD,
                'is_low_stock'    => $total <= StockService::LOW_STOCK_THRESHOLD,
            ];
        });

        return response()->json(['error' => false, 'data' => $data]);
    }

    public function lowStock(): JsonResponse
    {
        $products = $this->stockService->getLowStockProducts()
            ->map(fn ($p) => [
                'id'              => (int) $p->id,
                'name'            => $p->name,
                'category'        => $p->category?->name,
                'stock'           => (float) ($p->total_remaining ?? 0),
                'stock_available' => (float) ($p->total_remaining ?? 0),
                'reorder_point'   => StockService::LOW_STOCK_THRESHOLD,
            ]);

        return response()->json(['error' => false, 'data' => $products]);
    }

    public function movements(int $id): JsonResponse
    {
        $product = ZaloProduct::findOrFail($id);
        $movements = $this->stockService->getMovementHistory($id);

        return response()->json([
            'error'   => false,
            'product' => ['id' => (int) $product->id, 'name' => $product->name],
            'data'    => $movements,
        ]);
    }

    /**
     * Admin import: gọi service tạo batch trên farm mặc định.
     * Lưu ý: nếu chưa có farm active nào, service sẽ throw — admin phải tạo
     * farm "main" trước.
     */
    public function import(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity'    => 'required|numeric|min:0.01',
            'note'        => 'required|string|max:500',
            'expire_date' => 'nullable|date',
            'batch_date'  => 'nullable|date',
        ]);

        $adminId = auth()->id() ?? 0;
        try {
            $this->stockService->importStock(
                $id,
                (float) $request->quantity,
                $request->note,
                $adminId,
                $request->expire_date ?: null,
                $request->batch_date  ?: null,
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        $total = $this->stockService->getProductTotalRemaining($id);
        return response()->json([
            'error'   => false,
            'message' => 'Nhập kho thành công',
            'stock'   => $total,
        ]);
    }

    public function adjust(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0',
            'note'     => 'required|string|max:500',
        ]);

        $adminId = auth()->id() ?? 0;
        try {
            $this->stockService->adjustStock($id, (float) $request->quantity, $request->note, $adminId);
        } catch (\Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        $total = $this->stockService->getProductTotalRemaining($id);
        return response()->json([
            'error'   => false,
            'message' => 'Điều chỉnh tồn kho thành công',
            'stock'   => $total,
        ]);
    }

    /**
     * Aggregate quantity_remaining theo product_id. Trả về array<product_id, float>.
     */
    private function aggregateByProduct(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        return DB::table('farm_stock_batches')
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_remaining) AS total')
            ->pluck('total', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
