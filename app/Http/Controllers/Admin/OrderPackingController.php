<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\OrderFarmAssignment;
use App\Services\PackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin web — Phân công đóng gói đơn.
 *
 * Admin xem danh sách phiếu (order, farm) và gán nhân viên đóng gói. Logic
 * nghiệp vụ + audit log nằm trong PackingService (dùng chung với Mini App).
 */
class OrderPackingController extends Controller
{
    public function __construct(private PackingService $packing) {}

    /**
     * Danh sách phiếu đóng gói — filter theo farm / status / packer.
     * Chỉ liệt kê phiếu của đơn còn đang xử lý (chưa giao/huỷ) cho gọn.
     */
    public function index(Request $request)
    {
        // Đồng bộ phiếu trước khi liệt kê — tạo cho mọi cặp (order, farm) đang
        // xử lý mà chưa có phiếu (đơn tạo sau migration backfill).
        $this->ensureAssignmentsExist();

        $query = OrderFarmAssignment::query()
            ->with(['farm', 'assignedCustomer', 'order'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'preparing', 'delivering']);
            });

        if ($request->filled('farm_id')) {
            $query->where('farm_id', $request->farm_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where('order_id', $term);
        }

        $assignments = $query->orderByDesc('order_id')->orderBy('farm_id')
            ->paginate(25)->withQueryString();

        $farms = Farm::active()->orderBy('name')->get(['id', 'name']);

        $statusOptions = [
            OrderFarmAssignment::STATUS_UNASSIGNED => 'Chưa gán',
            OrderFarmAssignment::STATUS_ASSIGNED   => 'Đã gán',
            OrderFarmAssignment::STATUS_PACKING    => 'Đang đóng gói',
            OrderFarmAssignment::STATUS_PACKED     => 'Đã đóng gói',
        ];

        // Map farm_id → danh sách thành viên (owner + staff) để render dropdown
        // gán packer trên từng dòng mà không bị N+1.
        $farmIds = $assignments->pluck('farm_id')->unique()->filter()->values();
        $membersByFarm = Customer::whereIn('farm_id', $farmIds)
            ->where('isActive', 1)
            ->orderByRaw("CASE WHEN farm_role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'farm_id', 'farm_role'])
            ->groupBy('farm_id');

        return view('admin.order_packing.index', compact(
            'assignments', 'farms', 'statusOptions', 'membersByFarm'
        ));
    }

    /**
     * Gán/đổi packer cho 1 phiếu. Actor = admin user đang đăng nhập.
     */
    public function assign(Request $request, int $assignmentId)
    {
        $data = $request->validate([
            'packer_customer_id' => 'required|integer|exists:customers,id',
        ]);

        $assignment = OrderFarmAssignment::findOrFail($assignmentId);
        $packer     = Customer::findOrFail($data['packer_customer_id']);

        try {
            $this->packing->assign($assignment, $packer, Auth::user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã phân công đơn #{$assignment->order_id} cho {$packer->name}.");
    }

    /**
     * Tạo phiếu hub 'unassigned' cho mọi đơn đang xử lý còn thiếu (1 phiếu/đơn
     * thuộc Package Hub). Idempotent — giống ensureAssignmentsExist của
     * FarmHubController. No-op nếu chưa cấu hình hub.
     */
    private function ensureAssignmentsExist(): void
    {
        $hub = Farm::primaryPackingHub();
        if (! $hub) {
            return;
        }

        $missing = DB::table('zalo_orders as o')
            ->leftJoin('order_farm_assignments as a', function ($join) use ($hub) {
                $join->on('a.order_id', '=', 'o.id')
                     ->where('a.farm_id', '=', $hub->id);
            })
            ->whereIn('o.status', ['pending', 'confirmed', 'preparing', 'delivering'])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('zalo_order_items as oi')
                  ->whereColumn('oi.order_id', 'o.id');
            })
            ->whereNull('a.id')
            ->select('o.id')
            ->distinct()
            ->pluck('id');

        if ($missing->isEmpty()) {
            return;
        }

        $now  = now();
        $rows = $missing->map(fn ($orderId) => [
            'order_id'   => $orderId,
            'farm_id'    => $hub->id,
            'status'     => OrderFarmAssignment::STATUS_UNASSIGNED,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('order_farm_assignments')->insertOrIgnore($chunk);
        }
    }
}
