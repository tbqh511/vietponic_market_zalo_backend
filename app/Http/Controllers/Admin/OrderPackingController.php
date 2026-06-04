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
     * Tạo phiếu 'unassigned' cho mọi cặp (order, farm) đang xử lý còn thiếu.
     * Idempotent — giống ensureAssignmentsExist của FarmHubController nhưng
     * quét toàn hệ thống (mọi farm).
     */
    private function ensureAssignmentsExist(): void
    {
        $missing = DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('order_farm_assignments as a', function ($join) {
                $join->on('a.order_id', '=', 'oi.order_id')
                     ->on('a.farm_id', '=', 'oi.farm_id');
            })
            ->whereNotNull('oi.farm_id')
            ->whereIn('o.status', ['pending', 'confirmed', 'preparing', 'delivering'])
            ->whereNull('a.id')
            ->select('oi.order_id', 'oi.farm_id')
            ->distinct()
            ->get();

        if ($missing->isEmpty()) {
            return;
        }

        $now  = now();
        $rows = $missing->map(fn ($p) => [
            'order_id'   => $p->order_id,
            'farm_id'    => $p->farm_id,
            'status'     => OrderFarmAssignment::STATUS_UNASSIGNED,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('order_farm_assignments')->insertOrIgnore($chunk);
        }
    }
}
