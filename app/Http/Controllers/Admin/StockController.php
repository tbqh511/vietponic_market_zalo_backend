<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request)
    {
        $query = ZaloProduct::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('stock_status')) {
            match ($request->stock_status) {
                'out'  => $query->where('stock', '<=', 0),
                'low'  => $query->whereRaw('stock > 0 AND stock <= reorder_point'),
                'ok'   => $query->whereRaw('stock > reorder_point'),
                default => null,
            };
        }

        $products   = $query->orderBy('name')->get();
        $categories = ZaloCategory::orderBy('id')->get();

        return view('admin.inventory.index', compact('products', 'categories'));
    }

    public function show(Request $request, ZaloProduct $inventory)
    {
        $movements = $this->stockService->getMovementHistory($inventory->id, 20);
        return view('admin.inventory.show', compact('inventory', 'movements'));
    }

    public function importForm(ZaloProduct $inventory)
    {
        return view('admin.inventory.import', compact('inventory'));
    }

    public function importStore(Request $request, ZaloProduct $inventory)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'note'     => 'required|string|max:500',
        ]);

        $this->stockService->importStock(
            $inventory->id,
            (int) $request->quantity,
            $request->note,
            auth()->id()
        );

        return redirect()
            ->route('inventory.show', $inventory->id)
            ->with('success', 'Nhập kho thành công. Tồn kho đã được cập nhật.');
    }

    public function adjust(Request $request, ZaloProduct $inventory)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
            'note'     => 'required|string|max:500',
        ]);

        $this->stockService->adjustStock(
            $inventory->id,
            (int) $request->quantity,
            $request->note,
            auth()->id()
        );

        return redirect()
            ->route('inventory.show', $inventory->id)
            ->with('success', 'Điều chỉnh tồn kho thành công.');
    }

    public function reorderPoint(Request $request, ZaloProduct $inventory)
    {
        $request->validate([
            'reorder_point' => 'required|integer|min:0',
        ]);

        $this->stockService->updateReorderPoint(
            $inventory->id,
            (int) $request->reorder_point,
            auth()->id()
        );

        return redirect()
            ->route('inventory.show', $inventory->id)
            ->with('success', 'Đã cập nhật ngưỡng cảnh báo tồn kho.');
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
}
