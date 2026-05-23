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
        $query = ZaloOrder::query()
            ->with(['customer', 'delivery'])
            ->withCount('items');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(function ($q) use ($term) {
                $q->where('id', $term)
                  ->orWhere('customer_id', $term)
                  ->orWhereHas('customer', function ($c) use ($term) {
                      $c->where('name', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%");
                  });
            });
        }

        // Summary stats trên TOÀN BỘ tập đã lọc (không chỉ trang hiện tại).
        $statsQuery   = (clone $query);
        $totalOrders  = (clone $statsQuery)->count();
        $totalRevenue = (clone $statsQuery)
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');
        $pendingCount = (clone $statsQuery)->where('status', 'pending')->count();
        $unpaidCount  = (clone $statsQuery)
            ->whereIn('payment_status', ['pending'])
            ->count();

        $orders = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        // Danh sách trạng thái cho dropdown filter.
        $statusOptions = [
            'pending'    => 'Chờ xác nhận',
            'confirmed'  => 'Đã xác nhận',
            'preparing'  => 'Đang chuẩn bị',
            'delivering' => 'Đang giao',
            'delivered'  => 'Đã giao',
            'cancelled'  => 'Đã huỷ',
        ];

        return view('admin.zalo_orders.index', compact(
            'orders', 'statusOptions',
            'totalOrders', 'totalRevenue', 'pendingCount', 'unpaidCount'
        ));
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

        // Guard: không cho phép hủy đơn đã delivered qua admin web
        if (isset($data['status']) && $data['status'] === 'cancelled') {
            if ($previousStatus === 'delivered') {
                return redirect()->back()->withErrors([
                    'status' => 'Không thể hủy đơn hàng đã giao thành công. '
                              . 'Liên hệ kế toán nếu cần xử lý hoàn tiền thủ công.'
                ]);
            }
            // Idempotent: đã cancelled rồi thì redirect thành công luôn
            if ($previousStatus === 'cancelled') {
                return redirect()->route('zalo-orders.show', $order->id)
                                 ->with('success', 'Đơn hàng đã ở trạng thái hủy.');
            }
        }

        // Chặn chuyển ngược từ delivering/delivered về pending/confirmed/preparing
        if (
            isset($data['status'])
            && in_array($previousStatus, ['delivering', 'delivered'], true)
            && in_array($data['status'], ['pending', 'confirmed', 'preparing'], true)
        ) {
            return redirect()->back()->withErrors([
                'status' => "Không thể chuyển đơn từ \"{$previousStatus}\" về \"{$data['status']}\"."
            ]);
        }

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
