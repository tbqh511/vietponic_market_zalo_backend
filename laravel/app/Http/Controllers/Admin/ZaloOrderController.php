<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZaloOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = ZaloOrder::query();
        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        $orders = $query->orderBy('id', 'desc')->get();
        return view('admin.zalo_orders.index', compact('orders'));
    }

    public function show($id)
    {
        $order = ZaloOrder::with(['items', 'delivery'])->findOrFail($id);
        return view('admin.zalo_orders.show', compact('order'));
    }

    public function edit($id)
    {
        $order = ZaloOrder::with(['items', 'delivery'])->findOrFail($id);
        return view('admin.zalo_orders.edit', compact('order'));
    }

    public function update(Request $request, $id)
    {
        $order = ZaloOrder::findOrFail($id);
        $data = $request->validate([
            'status' => 'nullable|string|max:255',
            'payment_status' => 'nullable|string|max:255',
            'received_at' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->update($data);
            // recalc total from items if needed
            $total = $order->items()->sum(DB::raw('price * quantity'));
            $order->total = $total;
            $order->save();
        });

        return redirect()->route('zalo-orders.show', $order->id)->with('success', 'Order updated');
    }

    public function destroy($id)
    {
        $order = ZaloOrder::findOrFail($id);
        $order->delete();
        return redirect()->route('zalo-orders.index')->with('success', 'Order deleted');
    }
}
