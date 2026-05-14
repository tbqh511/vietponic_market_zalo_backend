<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Http\Request;

class FarmStockController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request)
    {
        $q           = trim((string) $request->query('q', ''));
        $categoryId  = $request->query('category_id');
        $stockStatus = (string) $request->query('stock_status', 'all');
        $sort        = (string) $request->query('sort', 'name');
        $perPage     = (int) $request->query('per_page', 20);
        $perPage     = max(1, min($perPage, 100));

        $base = ZaloProduct::query();

        if ($q !== '') {
            $base->where('name', 'like', '%' . $q . '%');
        }
        if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'all') {
            $base->where('category_id', (int) $categoryId);
        }

        // Stats reflect the current search/category scope (not stock_status), so
        // the badges keep useful context even while filtering by status tab.
        $statsBase = (clone $base);
        $stats = [
            'total_products'     => (clone $statsBase)->count(),
            'low_stock_count'    => (clone $statsBase)->whereRaw('stock <= reorder_point')->where('stock', '>', 0)->count(),
            'out_of_stock_count' => (clone $statsBase)->where('stock', '<=', 0)->count(),
            'total_stock'        => (int) (clone $statsBase)->sum('stock'),
        ];

        switch ($stockStatus) {
            case 'low':
                $base->whereRaw('stock <= reorder_point')->where('stock', '>', 0);
                break;
            case 'out':
                $base->where('stock', '<=', 0);
                break;
            case 'in_stock':
                $base->where('stock', '>', 0)->whereRaw('stock > reorder_point');
                break;
        }

        switch ($sort) {
            case 'stock_asc':
                $base->orderBy('stock')->orderBy('name');
                break;
            case 'stock_desc':
                $base->orderByDesc('stock')->orderBy('name');
                break;
            case 'low_first':
                $base->orderByRaw('(stock - reorder_point) ASC')->orderBy('name');
                break;
            case 'name':
            default:
                $base->orderBy('name');
        }

        $paginator = $base->with('category')->paginate($perPage);

        $items = collect($paginator->items())->map(fn ($p) => [
            'id'              => $p->id,
            'name'            => $p->name,
            'category'        => $p->category?->name,
            'category_id'     => $p->category_id,
            'image_url'       => $p->image_url,
            'stock'           => (int) $p->stock,
            'stock_reserved'  => (int) $p->stock_reserved,
            'stock_available' => $p->stock_available,
            'reorder_point'   => (int) $p->reorder_point,
            'is_low_stock'    => $p->stock <= $p->reorder_point,
            'is_out_of_stock' => $p->stock <= 0,
        ]);

        return response()->json([
            'error' => false,
            'data'  => $items,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'stats' => $stats,
        ]);
    }

    public function movements(int $id)
    {
        $product = ZaloProduct::findOrFail($id);
        $movements = $this->stockService->getMovementHistory($id);

        return response()->json([
            'error'   => false,
            'product' => ['id' => $product->id, 'name' => $product->name],
            'data'    => $movements,
        ]);
    }

    public function import(Request $request, int $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'note'     => 'required|string|max:500',
        ]);

        $customerId = $request->attributes->get('zalo_customer_id');
        $this->stockService->importStockByFarm($id, (int) $request->quantity, $request->note, $customerId);

        $product = ZaloProduct::find($id);
        return response()->json([
            'error'           => false,
            'message'         => 'Nhập kho thành công',
            'stock'           => $product->stock,
            'stock_available' => $product->stock_available,
        ]);
    }

    public function export(Request $request, int $id)
    {
        $product = ZaloProduct::findOrFail($id);

        $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:' . $product->stock_available],
            'note'     => 'required|string|max:500',
        ]);

        $customerId = $request->attributes->get('zalo_customer_id');
        $this->stockService->exportStock($id, (int) $request->quantity, $request->note, $customerId);

        $product->refresh();
        return response()->json([
            'error'           => false,
            'message'         => 'Xuất kho thành công',
            'stock'           => $product->stock,
            'stock_available' => $product->stock_available,
        ]);
    }
}
