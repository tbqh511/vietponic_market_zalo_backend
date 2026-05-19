<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmPayout;
use App\Services\FarmDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admin web controller cho đối soát Farm (lệnh chi cho Farm Partner).
 *
 * Lifecycle quyết định ON-DEMAND:
 *   - Admin chọn farm + period → preview revenue qua FarmDashboardService.
 *   - Submit → INSERT FarmPayout status='pending'.
 *   - Admin upload UNC + bấm "Xác nhận đã thanh toán" → status='paid'.
 *   - Có thể "Huỷ" payout (status='cancelled') nếu chốt nhầm.
 *
 * Không có cron auto-snapshot ở giai đoạn này — chốt với user để giảm độ phức
 * tạp; có thể bổ sung sau khi volume tăng.
 */
class FarmPayoutController extends Controller
{
    /**
     * Danh sách lệnh chi. Filter: farm, status, khoảng kỳ (period_start/end overlap).
     */
    public function index(Request $request)
    {
        $query = FarmPayout::query()->with('farm.owner');

        if ($request->filled('farm_id')) {
            $query->where('farm_id', $request->farm_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Filter overlap: payout có giao với khoảng [from, to] do admin chọn.
        if ($request->filled('date_from')) {
            $query->whereDate('period_end', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('period_start', '<=', $request->date_to);
        }

        $payouts = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $farms   = Farm::orderBy('name')->get(['id', 'code', 'name']);

        return view('admin.farm_payouts.index', compact('payouts', 'farms'));
    }

    /**
     * Form tạo lệnh chi. GET có thể truyền farm_id + period_start/end để preview
     * revenue ngay trên trang trước khi submit.
     */
    public function create(Request $request, FarmDashboardService $dashboard)
    {
        $farms = Farm::active()->with('owner')->orderBy('name')->get();

        $preview = null;
        if ($request->filled(['farm_id', 'period_start', 'period_end'])) {
            $farm = Farm::find($request->farm_id);
            if ($farm) {
                try {
                    $overview = $dashboard->getOverview(
                        $farm,
                        'custom',
                        $request->period_start,
                        $request->period_end
                    );
                    $gross   = (float) $overview['revenue'];
                    $netRaw  = round($gross * (float) $farm->commission_rate, 2);
                    $preview = [
                        'farm'          => $farm,
                        'gross_revenue' => $gross,
                        'items_sold'    => (float) $overview['items_sold'],
                        'orders_count'  => (int) $overview['orders_count'],
                        'net_payout'    => $netRaw,
                    ];
                } catch (\InvalidArgumentException $e) {
                    return back()->with('error', 'Khoảng kỳ không hợp lệ: ' . $e->getMessage())->withInput();
                }
            }
        }

        return view('admin.farm_payouts.create', compact('farms', 'preview'));
    }

    public function store(Request $request, FarmDashboardService $dashboard)
    {
        $data = $request->validate([
            'farm_id'      => 'required|integer|exists:farms,id',
            'period_start' => 'required|date|before_or_equal:period_end',
            'period_end'   => 'required|date|before_or_equal:today',
            'adjustment'   => 'nullable|numeric',
            'note'         => 'nullable|string|max:1000',
        ]);

        $farm = Farm::findOrFail($data['farm_id']);

        // Tính lại từ service tại thời điểm submit (chứ không tin số trên form)
        // để tránh user fudge giá trị qua devtools.
        $overview = $dashboard->getOverview($farm, 'custom', $data['period_start'], $data['period_end']);
        $gross    = (float) $overview['revenue'];
        $itemsSold = (float) $overview['items_sold'];

        $adjustment = isset($data['adjustment']) ? (float) $data['adjustment'] : 0.0;
        $net = round($gross * (float) $farm->commission_rate + $adjustment, 2);

        $payout = DB::transaction(function () use ($farm, $data, $gross, $itemsSold, $adjustment, $net) {
            return FarmPayout::create([
                'farm_id'       => $farm->id,
                'period_start'  => $data['period_start'],
                'period_end'    => $data['period_end'],
                'total_sold'    => $itemsSold,
                'gross_revenue' => $gross,
                'adjustment'    => $adjustment,
                'net_payout'    => $net,
                'status'        => 'pending',
                'note'          => $data['note'] ?? null,
            ]);
        });

        return redirect()->route('farm-payouts.show', $payout->id)
            ->with('success', 'Đã tạo lệnh chi #' . $payout->id . '. Thực hiện chuyển khoản và xác nhận sau.');
    }

    public function show(int $id)
    {
        $payout = FarmPayout::with('farm.owner')->findOrFail($id);
        return view('admin.farm_payouts.show', compact('payout'));
    }

    /**
     * Xác nhận đã thanh toán: upload ảnh UNC bắt buộc + transaction_ref optional.
     * File lưu vào disk public; path tương đối lưu trong proof_image_path.
     */
    public function markPaid(Request $request, int $id)
    {
        $payout = FarmPayout::findOrFail($id);

        if ($payout->status !== 'pending') {
            return back()->with('error', 'Chỉ payout ở trạng thái "pending" mới có thể đánh dấu đã chi.');
        }

        $data = $request->validate([
            'proof_image'     => 'required|file|image|max:5120',  // 5 MB
            'transaction_ref' => 'nullable|string|max:120',
            'payment_method'  => 'nullable|string|max:60',
        ]);

        // store('farm_payouts', 'public') tự sinh hash filename — không có
        // nguy cơ trùng tên file giữa các payout.
        $path = $request->file('proof_image')->store('farm_payouts', 'public');

        $payout->status           = 'paid';
        $payout->paid_at          = Carbon::now();
        $payout->proof_image_path = $path;
        $payout->transaction_ref  = $data['transaction_ref'] ?? null;
        $payout->payment_method   = $data['payment_method'] ?? 'bank_transfer';
        $payout->save();

        return redirect()->route('farm-payouts.show', $payout->id)
            ->with('success', 'Đã xác nhận thanh toán payout #' . $payout->id . '.');
    }

    public function cancel(Request $request, int $id)
    {
        $payout = FarmPayout::findOrFail($id);

        if ($payout->status === 'paid') {
            return back()->with('error', 'Payout đã thanh toán — không thể huỷ. Tạo lệnh điều chỉnh ngược nếu cần.');
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $payout->status = 'cancelled';
        $oldNote        = $payout->note ? rtrim($payout->note) . "\n" : '';
        $payout->note   = $oldNote . '[CANCELLED ' . Carbon::now()->format('Y-m-d H:i') . '] ' . ($data['reason'] ?? '');
        $payout->save();

        return redirect()->route('farm-payouts.show', $payout->id)
            ->with('success', 'Đã huỷ payout.');
    }
}
