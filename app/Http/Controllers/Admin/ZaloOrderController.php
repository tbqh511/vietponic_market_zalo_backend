<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloOrder;
use App\Models\ZaloOrderItem;
use App\Models\ZaloDelivery;
use App\Services\StockService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZaloOrderController extends Controller
{
    public function __construct(
        private StockService $stockService,
        private RefundService $refundService,
    ) {}

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
        $order = ZaloOrder::with(['items', 'delivery', 'trackingEvents'])->findOrFail($id);
        return view('admin.zalo_orders.show', compact('order'));
    }

    /**
     * Retry tạo đơn VTP cho 1 order shipping chưa có vtp_order_number.
     */
    public function retryVtp($id, \App\Services\VtpOrderService $svc)
    {
        $order = ZaloOrder::with(['items', 'delivery'])->findOrFail($id);
        try {
            $data = $svc->dispatchOrderToVtp($order);
            return redirect()
                ->route('zalo-orders.show', $order->id)
                ->with('success', 'Đã tạo đơn VTP: ' . ($data['ORDER_NUMBER'] ?? '?'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('zalo-orders.show', $order->id)
                ->with('error', 'Tạo đơn VTP thất bại: ' . $e->getMessage());
        }
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

        $previousStatus = $order->status;

        DB::transaction(function () use ($order, $data, $previousStatus) {
            // Farm Partner Hub: admin web chuyển sang 'delivered' → chốt delivered_at.
            // Set trước update để dính vào cùng query, idempotent với delivered_at có sẵn.
            if (
                isset($data['status'])
                && $data['status'] === 'delivered'
                && $previousStatus !== 'delivered'
                && empty($order->delivered_at)
            ) {
                $data['delivered_at'] = now();
            }
            $order->update($data);
            // recalc total from items if needed
            $total = $order->items()->sum(DB::raw('price * quantity'));
            $order->total = $total;
            $order->save();
        });

        // Khi admin web chuyển sang cancelled: release stock + trigger refund flow.
        // Trước đây admin web không gọi 2 thứ này → stock bị orphan, không có refund.
        if (
            isset($data['status'])
            && $data['status'] === 'cancelled'
            && $previousStatus !== 'cancelled'
        ) {
            $order->update([
                'cancelled_at'        => now(),
                'cancelled_by'        => 'admin',
                'cancellation_reason' => 'Admin huỷ qua admin dashboard',
            ]);
            try {
                $this->stockService->releaseReservation($order->id);
            } catch (\Throwable $e) {
                Log::error('Admin web: releaseReservation failed', [
                    'order_id' => $order->id,
                    'message'  => $e->getMessage(),
                ]);
            }
            try {
                $this->refundService->processCancellationRefund($order->fresh(), 'admin');
            } catch (\Throwable $e) {
                Log::error('Admin web: processCancellationRefund failed', [
                    'order_id' => $order->id,
                    'message'  => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('zalo-orders.show', $order->id)->with('success', 'Order updated');
    }

    public function destroy($id)
    {
        $order = ZaloOrder::findOrFail($id);
        $order->delete();
        return redirect()->route('zalo-orders.index')->with('success', 'Order deleted');
    }
}
