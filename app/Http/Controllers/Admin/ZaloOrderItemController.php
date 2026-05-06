<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloOrderItem;
use App\Models\ZaloOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZaloOrderItemController extends Controller
{
    public function create($orderId = null)
    {
        $order = $orderId ? ZaloOrder::findOrFail($orderId) : null;
        return view('admin.zalo_order_items.create', compact('order'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:zalo_orders,id',
            'product_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|string',
            'detail' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            ZaloOrderItem::create($data + ['price' => $data['price']]);
            // update order total
            $order = ZaloOrder::find($data['order_id']);
            $order->total = $order->items()->sum(DB::raw('price * quantity'));
            $order->save();
        });

        return redirect()->route('zalo-orders.show', $data['order_id'])->with('success', 'Order item added');
    }

    public function edit($id)
    {
        $item = ZaloOrderItem::findOrFail($id);
        return view('admin.zalo_order_items.edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $item = ZaloOrderItem::findOrFail($id);
        $data = $request->validate([
            'product_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|string',
            'detail' => 'nullable|string',
        ]);

        DB::transaction(function () use ($item, $data) {
            $item->update($data);
            $order = ZaloOrder::find($item->order_id);
            $order->total = $order->items()->sum(DB::raw('price * quantity'));
            $order->save();
        });

        return redirect()->route('zalo-orders.show', $item->order_id)->with('success', 'Order item updated');
    }

    public function destroy($id)
    {
        $item = ZaloOrderItem::findOrFail($id);
        $orderId = $item->order_id;
        $item->delete();
        // recalc total
        $order = ZaloOrder::find($orderId);
        if ($order) {
            $order->total = $order->items()->sum(DB::raw('price * quantity'));
            $order->save();
        }
        return redirect()->route('zalo-orders.show', $orderId)->with('success', 'Order item deleted');
    }
}
