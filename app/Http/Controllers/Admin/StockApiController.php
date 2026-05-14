<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockApiController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index()
    {
        $products = ZaloProduct::with('category')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'category'        => $p->category?->name,
                'stock'           => $p->stock,
                'stock_reserved'  => $p->stock_reserved,
                'stock_available' => $p->stock_available,
                'reorder_point'   => $p->reorder_point,
                'is_low_stock'    => $p->stock <= $p->reorder_point,
            ]);

        return response()->json(['error' => false, 'data' => $products]);
    }

    public function lowStock()
    {
        $products = $this->stockService->getLowStockProducts()
            ->map(fn ($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'category'        => $p->category?->name,
                'stock'           => $p->stock,
                'stock_available' => $p->stock_available,
                'reorder_point'   => $p->reorder_point,
            ]);

        return response()->json(['error' => false, 'data' => $products]);
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

        $adminId = auth()->id() ?? 0;
        $this->stockService->importStock($id, (int) $request->quantity, $request->note, $adminId);

        $product = ZaloProduct::find($id);
        return response()->json([
            'error'   => false,
            'message' => 'Nhập kho thành công',
            'stock'   => $product->stock,
        ]);
    }

    public function adjust(Request $request, int $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
            'note'     => 'required|string|max:500',
        ]);

        $adminId = auth()->id() ?? 0;
        $this->stockService->adjustStock($id, (int) $request->quantity, $request->note, $adminId);

        $product = ZaloProduct::find($id);
        return response()->json([
            'error'   => false,
            'message' => 'Điều chỉnh tồn kho thành công',
            'stock'   => $product->stock,
        ]);
    }
}
