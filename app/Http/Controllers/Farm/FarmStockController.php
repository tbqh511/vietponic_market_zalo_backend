<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Http\Request;

class FarmStockController extends Controller
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
