<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloOrder;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Admin dashboard cho các đơn refund_status='pending_manual'.
 *
 * Refund của BANK/MOMO (và ZaloPay fallback) đều rơi vào nhánh manual để kế toán
 * chuyển tiền tay rồi confirm. Page này list các đơn đang chờ + nút confirm inline.
 *
 * Routes:
 *   GET  /admin/refunds/pending          → index (list + filters)
 *   POST /admin/refunds/{id}/confirm     → confirm 1 đơn đã hoàn tiền
 *   GET  /admin/refunds/pending-count    → JSON count cho badge sidebar
 */
class RefundController extends Controller
{
    private const COUNT_CACHE_KEY = 'admin.refunds.pending_count';
    private const COUNT_CACHE_TTL = 60; // seconds

    public function __construct(private RefundService $refundService) {}

    public function index(Request $request)
    {
        $query = ZaloOrder::query()
            ->with(['customer:id,name,mobile', 'items', 'delivery'])
            ->where('refund_status', 'pending_manual');

        // Filter: payment method (BANK / MOMO / ZALOPAY fallback)
        $method = trim((string) $request->input('method', ''));
        if ($method !== '') {
            $query->where('payment_method', 'like', $method . '%');
        }

        // Filter: date range (default 30 ngày gần nhất)
        $from = $request->input('from');
        $to   = $request->input('to');
        if ($from) {
            $query->where('cancelled_at', '>=', $from);
        }
        if ($to) {
            $query->where('cancelled_at', '<=', $to . ' 23:59:59');
        }
        if (!$from && !$to) {
            $query->where('cancelled_at', '>=', now()->subDays(30));
        }

        $orders = $query->orderBy('cancelled_at', 'desc')->paginate(50)->withQueryString();

        // Stats cho header dashboard
        $totalCount  = ZaloOrder::where('refund_status', 'pending_manual')->count();
        $totalAmount = ZaloOrder::where('refund_status', 'pending_manual')->sum('refund_amount');

        return view('admin.refunds.pending', [
            'orders'      => $orders,
            'totalCount'  => $totalCount,
            'totalAmount' => $totalAmount,
            'filterMethod' => $method,
            'filterFrom'  => $from,
            'filterTo'    => $to,
        ]);
    }

    public function confirm(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $order = ZaloOrder::find($id);
        if (!$order) {
            return back()->with('error', 'Không tìm thấy đơn hàng');
        }

        if ($order->refund_status !== 'pending_manual') {
            return back()->with(
                'error',
                'Đơn hàng không ở trạng thái chờ hoàn tiền (hiện tại: ' . ($order->refund_status ?? 'null') . ')'
            );
        }

        try {
            $this->refundService->confirmManualRefund($order, $request->input('note'));
            Cache::forget(self::COUNT_CACHE_KEY);
            Log::info('Admin confirmed manual refund', [
                'order_id' => $order->id,
                'admin_id' => auth()->id(),
                'note'     => $request->input('note'),
            ]);
            return back()->with('success', "Đã xác nhận hoàn tiền cho đơn #{$order->id}");
        } catch (\Throwable $e) {
            Log::error('Admin confirm refund failed', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);
            return back()->with('error', 'Xác nhận hoàn tiền thất bại: ' . $e->getMessage());
        }
    }

    /**
     * JSON count endpoint cho counter badge. Cache 60s để tránh hammer DB
     * khi nhiều admin tab cùng mở. Cache invalidate sau mỗi confirm().
     */
    public function count()
    {
        $count = Cache::remember(
            self::COUNT_CACHE_KEY,
            self::COUNT_CACHE_TTL,
            fn () => ZaloOrder::where('refund_status', 'pending_manual')->count()
        );
        return response()->json(['count' => $count]);
    }
}
