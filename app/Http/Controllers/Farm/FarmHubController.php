<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Services\FarmDashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * FarmHubController — endpoints cho Farm Partner Hub dashboard.
 *
 * Tất cả route dưới middleware `zalo.farm` (EnsureFarmPartner) — middleware
 * này đã verify customer là farm partner đã duyệt + có farm active, và đính
 * model Farm vào request attributes (`$request->attributes->get('farm')`).
 * Controller KHÔNG cần re-check role hay tự tra farm — middleware đã làm.
 *
 * Convention response: { error: bool, data?: ..., message?: string }.
 * Khớp với các endpoint customer hiện có để frontend dùng chung extractArray.
 */
class FarmHubController extends Controller
{
    public function __construct(private FarmDashboardService $dashboard) {}

    /**
     * GET /farm/hub/profile — thông tin farm đang đăng nhập.
     * Dùng để hiển thị header (tên/logo) trên dashboard, tránh frontend phải
     * tự tra. Không trả các field nội bộ như approved_by / commission_rate.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var \App\Models\Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        return response()->json([
            'error' => false,
            'data'  => [
                'id'             => (int) $farm->id,
                'code'           => $farm->code,
                'name'           => $farm->name,
                'logo'           => $farm->logo,
                'cover_image'    => $farm->cover_image,
                'description'    => $farm->description,
                'address'        => $farm->address,
                'payment_cycle'  => $farm->payment_cycle,
                // commission_rate = phần farm GIỮ LẠI (vd 0.85 = farm nhận 85%).
                // Phí Vietponics = 1 - commission_rate (vd 15%). FE dùng để hiển
                // thị dòng "Phí Vietponics (x%)" trong breakdown payout.
                'commission_rate' => (float) $farm->commission_rate,
                'approved_at'    => optional($farm->approved_at)->toIso8601String(),
                // Vai trò người đang đăng nhập trong farm — FE dùng để bật/tắt UI
                // chỉ-owner (vd nút "Phân công") và xác định đơn của-mình.
                'viewer'         => [
                    'customer_id' => (int) $customer->id,
                    'name'        => $customer->name ?: 'Thành viên',
                    'farm_role'   => $customer->farm_role, // 'owner' | 'staff'
                    'is_owner'    => $customer->isFarmOwner(),
                ],
            ],
        ]);
    }

    /**
     * GET /farm/hub/overview?range=today|7d|30d|custom[&from=&to=]
     */
    public function overview(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'range' => 'nullable|string|in:today,7d,30d,custom',
            'from'  => 'nullable|date_format:Y-m-d',
            'to'    => 'nullable|date_format:Y-m-d',
        ]);

        $range = (string) $request->query('range', '7d');

        try {
            $data = $this->dashboard->getOverview(
                $farm,
                $range,
                $request->query('from'),
                $request->query('to'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * GET /farm/hub/revenue?range=...&bucket=day|week
     */
    public function revenue(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'range'  => 'nullable|string|in:today,7d,30d,custom',
            'bucket' => 'nullable|string|in:day,week',
            'from'   => 'nullable|date_format:Y-m-d',
            'to'     => 'nullable|date_format:Y-m-d',
        ]);

        $range  = (string) $request->query('range', '7d');
        $bucket = (string) $request->query('bucket', 'day');

        try {
            $series = $this->dashboard->getRevenueTimeseries(
                $farm,
                $range,
                $bucket,
                $request->query('from'),
                $request->query('to'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'error' => false,
            'data'  => [
                'bucket' => $bucket,
                'range'  => $range,
                'series' => $series,
            ],
        ]);
    }

    /**
     * GET /farm/hub/top-products?range=...&limit=10
     */
    public function topProducts(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'range' => 'nullable|string|in:today,7d,30d,custom',
            'limit' => 'nullable|integer|min:1|max:100',
            'from'  => 'nullable|date_format:Y-m-d',
            'to'    => 'nullable|date_format:Y-m-d',
        ]);

        $range = (string) $request->query('range', '30d');
        $limit = (int) $request->query('limit', 10);

        try {
            $rows = $this->dashboard->getTopProducts(
                $farm,
                $range,
                $limit,
                $request->query('from'),
                $request->query('to'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['error' => false, 'data' => $rows]);
    }

    /**
     * GET /farm/hub/inventory — snapshot tồn kho hiện tại (không nhận range).
     */
    public function inventory(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $data = $this->dashboard->getInventorySummary($farm);

        return response()->json(['error' => false, 'data' => $data]);
    }

    // ─── Alias methods cho route phẳng (/farm/*) ─────────────────────────────
    // FE có thể dùng URL phẳng (theo spec mới) hoặc nested /farm/hub/*. Các
    // method dưới chỉ wrap/compose method gốc, KHÔNG duplicate business logic.

    /**
     * GET /farm/analytics?range=...&bucket=day|week&limit=10
     * Gộp overview + top-products + revenue series vào 1 payload để FE đỡ
     * phải call 3 endpoint riêng cho trang phân tích.
     */
    public function analytics(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'range'  => 'nullable|string|in:today,7d,30d,custom',
            'bucket' => 'nullable|string|in:day,week',
            'limit'  => 'nullable|integer|min:1|max:100',
            'from'   => 'nullable|date_format:Y-m-d',
            'to'     => 'nullable|date_format:Y-m-d',
        ]);

        $range  = (string) $request->query('range', '30d');
        $bucket = (string) $request->query('bucket', 'day');
        $limit  = (int) $request->query('limit', 10);
        $from   = $request->query('from');
        $to     = $request->query('to');

        try {
            $overview = $this->dashboard->getOverview($farm, $range, $from, $to);
            $series   = $this->dashboard->getRevenueTimeseries($farm, $range, $bucket, $from, $to);
            $top      = $this->dashboard->getTopProducts($farm, $range, $limit, $from, $to);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'error' => false,
            'data'  => [
                'overview'      => $overview,
                'revenue'       => ['bucket' => $bucket, 'range' => $range, 'series' => $series],
                'top_products'  => $top,
            ],
        ]);
    }

    /**
     * POST /farm/request-partnership
     * Customer (chưa là partner) xin trở thành farm partner.
     * Stub: chưa có flow approval — return 501 để FE biết.
     */
    public function requestPartnership(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => true,
            'message' => 'Tính năng đăng ký Farm Partner đang được phát triển. Vui lòng liên hệ admin Vietponics để được duyệt thủ công.',
        ], 501);
    }

    /**
     * GET /farm/products/today — per-product breakdown cho dashboard wireframe.
     *
     * Mỗi row gồm:
     *   - sản phẩm farm có nhập trong ngày (batch.batch_date = today VN tz) HOẶC
     *     có bán trong ngày (order_items.farm_id + delivered_at hoặc created_at today)
     *   - stocked: tổng quantity_in của batch hôm nay
     *   - sold: tổng quantity bán hôm nay (đơn delivering+delivered)
     *   - remaining: tồn còn lại của tất cả active batch (không chỉ batch hôm nay)
     *   - revenue: SUM(price * quantity)
     *   - sellthrough_pct: sold / stocked * 100 (cap 999 để tránh số xấu khi
     *     stocked=0 mà có bán cũ)
     *   - status: 'good' | 'warning' | 'danger' — màu cho UI:
     *       danger  >= 95% sellthrough hoặc remaining=0
     *       warning >= 70% hoặc remaining < 5kg
     *       good    còn lại
     */
    public function productsToday(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $tz    = 'Asia/Ho_Chi_Minh';
        $vnNow = \Carbon\Carbon::now($tz);
        $today = $vnNow->toDateString();

        // Ngày hôm nay VN tz dưới dạng UTC range — tránh CONVERT_TZ (MySQL-only).
        // 00:00 VN = 17:00 UTC hôm trước; 24:00 VN = 17:00 UTC hôm nay.
        $todayStartUtc = $vnNow->copy()->startOfDay()->setTimezone('UTC');
        $todayEndUtc   = $vnNow->copy()->endOfDay()->setTimezone('UTC');

        // 1. Stocked hôm nay (batches có batch_date = today). Group by product.
        $stockedToday = \Illuminate\Support\Facades\DB::table('farm_stock_batches')
            ->where('farm_id', $farm->id)
            ->whereDate('batch_date', $today)
            ->selectRaw('product_id, SUM(quantity_in) AS stocked')
            ->groupBy('product_id')
            ->pluck('stocked', 'product_id');

        // 2. Sold hôm nay: dùng created_at thay vì delivered_at vì dashboard
        // hiện tại muốn "đang bán hôm nay" (gồm cả đơn chưa delivered).
        // Lấy đơn ở trạng thái đã/đang giao (delivering+delivered) — KHÔNG
        // tính pending/confirmed/preparing vì có thể huỷ.
        // So sánh UTC range thay vì CONVERT_TZ để tương thích SQLite (test env).
        $soldRows = \Illuminate\Support\Facades\DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.farm_id', $farm->id)
            ->whereIn('o.status', ['delivering', 'delivered'])
            ->whereBetween('o.created_at', [$todayStartUtc, $todayEndUtc])
            ->selectRaw('
                oi.product_id AS product_id,
                COALESCE(SUM(oi.quantity), 0) AS sold,
                COALESCE(SUM(oi.price * oi.quantity), 0) AS revenue
            ')
            ->groupBy('oi.product_id')
            ->get();

        $soldByProduct = [];
        foreach ($soldRows as $r) {
            $soldByProduct[(int) $r->product_id] = [
                'sold'    => (float) $r->sold,
                'revenue' => (float) $r->revenue,
            ];
        }

        // 3. Remaining: tổng quantity_remaining của tất cả active batch (không
        // giới hạn ngày). Đây là "còn bao nhiêu để bán tiếp" — không phải tồn
        // riêng của batch hôm nay.
        $remainingRows = \Illuminate\Support\Facades\DB::table('farm_stock_batches')
            ->where('farm_id', $farm->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->selectRaw('product_id, SUM(quantity_remaining) AS remaining')
            ->groupBy('product_id')
            ->pluck('remaining', 'product_id');

        // 4. Gộp danh sách product cần hiển thị = union(stockedToday, soldToday).
        $productIds = array_unique(array_merge(
            array_keys($stockedToday->toArray()),
            array_keys($soldByProduct),
        ));

        if (empty($productIds)) {
            return response()->json(['error' => false, 'data' => []]);
        }

        // 5. Lấy tên sản phẩm 1 query.
        $names = \Illuminate\Support\Facades\DB::table('zalo_products')
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        $rows = [];
        foreach ($productIds as $pid) {
            $stocked   = (float) ($stockedToday[$pid] ?? 0);
            $sold      = (float) ($soldByProduct[$pid]['sold'] ?? 0);
            $revenue   = (float) ($soldByProduct[$pid]['revenue'] ?? 0);
            $remaining = (float) ($remainingRows[$pid] ?? 0);

            // sellthrough = sold/stocked. Khi stocked=0 mà có bán → 999 (sentinel
            // "cháy hàng / bán âm" để FE highlight đỏ); UI cap về 100% trong text.
            if ($stocked > 0) {
                $sellthrough = round($sold / $stocked * 100, 1);
            } elseif ($sold > 0) {
                $sellthrough = 999.0;
            } else {
                $sellthrough = 0.0;
            }

            // Status màu — theo wireframe:
            //   danger = đã hết hoặc gần hết (95%+ hoặc remaining=0 mà còn nhu cầu)
            //   warning = đang bán nhanh (70%+ hoặc còn rất ít)
            //   good = còn nhiều, bình thường
            if ($remaining <= 0.01 && $sold > 0) {
                $status = 'danger';
            } elseif ($sellthrough >= 95) {
                $status = 'danger';
            } elseif ($sellthrough >= 70 || $remaining < 5) {
                $status = 'warning';
            } else {
                $status = 'good';
            }

            $rows[] = [
                'product_id'       => (int) $pid,
                'name'             => (string) ($names[$pid] ?? "Sản phẩm #{$pid}"),
                'stocked'          => round($stocked, 2),
                'sold'             => round($sold, 2),
                'remaining'        => round($remaining, 2),
                'revenue'          => round($revenue, 2),
                'sellthrough_pct'  => $sellthrough,
                'status'           => $status,
            ];
        }

        // Sort theo revenue desc — sản phẩm bán chạy lên đầu.
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        // 6. AI hint đơn giản — theo spec design doc:
        //   - sellthrough >= 95% → nhập thêm (qty_in * 1.3)
        //   - sau 14h VN + sellthrough < 30% với stocked > 5 → flash sale
        $vnNow = \Carbon\Carbon::now($tz);
        $hint  = $this->buildAiHint($rows, $vnNow->hour);

        return response()->json([
            'error' => false,
            'data'  => [
                'products' => $rows,
                'hint'     => $hint,
            ],
        ]);
    }

    /**
     * Sinh 1 AI hint duy nhất (priority: cháy hàng > bán chậm > null).
     * Trả về null nếu không có hint nào áp dụng — FE ẩn card.
     *
     * @param array $rows  rows từ productsToday
     * @param int   $hour  giờ hiện tại VN (0-23)
     */
    private function buildAiHint(array $rows, int $hour): ?array
    {
        // Ưu tiên 1: có sản phẩm cháy hàng (sellthrough ≥ 95%) → suggest restock.
        foreach ($rows as $r) {
            if ($r['sellthrough_pct'] >= 95 && $r['stocked'] > 0) {
                $suggestQty = (int) ceil($r['stocked'] * 1.3);
                return [
                    'type'    => 'restock',
                    'product' => $r['name'],
                    'message' => "{$r['name']} đang cháy hàng. Nhập thêm {$suggestQty}kg cho ngày mai?",
                ];
            }
        }

        // Ưu tiên 2: sau 14h mà có sản phẩm bán chậm (<30%, stocked > 5kg)
        // → suggest flash sale.
        if ($hour >= 14) {
            foreach ($rows as $r) {
                if ($r['sellthrough_pct'] < 30 && $r['stocked'] > 5) {
                    return [
                        'type'    => 'flash_sale',
                        'product' => $r['name'],
                        'message' => "{$r['name']} bán chậm hôm nay ({$r['sellthrough_pct']}%). Cân nhắc giảm giá nhanh chiều nay?",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * GET /farm/orders/incoming — order_items thuộc farm đang chờ giao.
     *
     * Lấy đơn ở trạng thái pending/confirmed/preparing/delivering (chưa
     * delivered hoặc cancelled). Mỗi row 1 order_item — đơn có nhiều item
     * cùng farm sẽ xuất hiện nhiều lần (FE group theo order_id nếu cần).
     *
     * Limit 50 row mới nhất, sort theo zalo_orders.created_at desc.
     */
    public function incomingOrders(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var \App\Models\Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        // Đảm bảo mỗi cặp (order, farm) đang xử lý đều có phiếu đóng gói — đơn
        // tạo sau backfill migration chưa có row, lazily tạo ở đây (unassigned).
        $this->ensureAssignmentsExist($farm->id);

        $rows = \Illuminate\Support\Facades\DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('zalo_deliveries as d', 'd.order_id', '=', 'o.id')
            ->leftJoin('order_farm_assignments as a', function ($join) use ($farm) {
                $join->on('a.order_id', '=', 'oi.order_id')
                     ->where('a.farm_id', '=', $farm->id);
            })
            // Tên người đang đóng gói — để FE hiện "Đang đóng: NV. Tuấn" /
            // "Đóng bởi: NV. Hà". leftJoin vì phiếu có thể chưa ai nhận.
            ->leftJoin('customers as pc', 'pc.id', '=', 'a.assigned_customer_id')
            ->where('oi.farm_id', $farm->id)
            ->whereIn('o.status', ['pending', 'confirmed', 'preparing', 'delivering'])
            ->orderByDesc('o.created_at')
            ->limit(200)
            ->selectRaw('
                oi.id AS item_id,
                oi.order_id AS order_id,
                oi.product_id AS product_id,
                oi.name AS product_name,
                oi.quantity AS quantity,
                oi.price AS price,
                o.status AS order_status,
                o.created_at AS order_created_at,
                o.total AS order_total,
                d.name AS customer_name,
                d.phone AS customer_phone,
                d.address AS delivery_address,
                d.type AS delivery_type,
                d.station_name AS station_name,
                a.status AS assignment_status,
                a.assigned_customer_id AS assigned_customer_id,
                a.packing_started_at AS packing_started_at,
                a.packed_at AS packed_at,
                pc.name AS assigned_customer_name
            ')
            ->get();

        // Mô hình self-claim: staff THẤY mọi đơn của farm (để nhận đơn chưa ai
        // nhận / thấy đơn người khác đang đóng bị khoá). FE quyết định nút theo
        // assignment_status + is_mine; backend KHÔNG lọc theo người gán nữa.
        // Bảo mật vẫn giữ: thông tin KH che server-side cho mọi vai trò.
        $data = $rows
            ->map(fn ($r) => [
                'item_id'             => (int) $r->item_id,
                'order_id'            => (int) $r->order_id,
                'product_id'          => (int) $r->product_id,
                'product_name'        => (string) $r->product_name,
                'quantity'            => (float) $r->quantity,
                'price'               => (float) $r->price,
                'order_status'        => (string) $r->order_status,
                'order_created_at'    => $r->order_created_at,
                'order_total'         => (float) $r->order_total,
                'is_pickup'           => $r->delivery_type === 'pickup',
                'station_name'        => $r->station_name,
                // Thông tin KH đã che server-side — staff không bao giờ nhận bản đầy đủ.
                'customer_name'       => $r->customer_name,
                'customer_phone'      => \App\Support\ContactMasker::maskPhone($r->customer_phone),
                'delivery_address'    => \App\Support\ContactMasker::maskAddress($r->delivery_address),
                // Trạng thái đóng gói + ai đang/đã đóng + mốc thời gian.
                'assignment_status'   => $r->assignment_status ?? \App\Models\OrderFarmAssignment::STATUS_UNASSIGNED,
                'assigned_customer_id'=> $r->assigned_customer_id !== null ? (int) $r->assigned_customer_id : null,
                'assigned_customer_name' => $r->assigned_customer_name,
                'packing_started_at'  => $r->packing_started_at,
                'packed_at'           => $r->packed_at,
                'is_mine'             => $r->assigned_customer_id !== null
                    && (int) $r->assigned_customer_id === (int) $customer->id,
            ])
            ->values()
            ->all();

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * GET /farm/staff — danh sách thành viên farm có thể được gán đóng gói
     * (owner + staff). Dùng cho picker "Phân công" của chủ farm trên Mini App.
     * Chỉ owner mới cần danh sách này; staff gọi vẫn trả nhưng FE không dùng.
     */
    public function staff(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $members = \App\Models\Customer::where('farm_id', $farm->id)
            ->where('isActive', 1)
            ->orderByRaw("CASE WHEN farm_role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'farm_role']);

        $data = $members->map(fn ($m) => [
            'id'        => (int) $m->id,
            'name'      => $m->name ?: 'Thành viên',
            'farm_role' => $m->farm_role,
        ])->all();

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * Tạo phiếu đóng gói 'unassigned' cho mọi cặp (order, farm) đang xử lý mà
     * chưa có phiếu. Idempotent — chạy mỗi lần load incoming để bắt kịp đơn mới.
     */
    private function ensureAssignmentsExist(int $farmId): void
    {
        $missing = \Illuminate\Support\Facades\DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('order_farm_assignments as a', function ($join) use ($farmId) {
                $join->on('a.order_id', '=', 'oi.order_id')
                     ->where('a.farm_id', '=', $farmId);
            })
            ->where('oi.farm_id', $farmId)
            ->whereIn('o.status', ['pending', 'confirmed', 'preparing', 'delivering'])
            ->whereNull('a.id')
            ->select('oi.order_id')
            ->distinct()
            ->pluck('order_id');

        if ($missing->isEmpty()) {
            return;
        }

        $now  = now();
        $rows = $missing->map(fn ($orderId) => [
            'order_id'   => $orderId,
            'farm_id'    => $farmId,
            'status'     => \App\Models\OrderFarmAssignment::STATUS_UNASSIGNED,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // insertOrIgnore tránh race khi 2 request cùng tạo (unique order_id+farm_id).
        \Illuminate\Support\Facades\DB::table('order_farm_assignments')->insertOrIgnore($rows);
    }

    /**
     * GET /farm/payouts — danh sách đợt thanh toán của farm.
     *
     * Sort theo period_end desc (đợt mới nhất trên cùng). Trả mọi status
     * (draft/pending/paid/cancelled) để farm thấy đầy đủ lịch sử + đợt hiện
     * tại (draft, do cron snapshot daily tích lũy).
     *
     * Limit mặc định 20, optional ?status=draft|pending|paid|cancelled để filter.
     */
    public function payouts(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'status' => 'nullable|string|in:draft,pending,paid,cancelled',
            'limit'  => 'nullable|integer|min:1|max:100',
        ]);

        $query = \App\Models\FarmPayout::where('farm_id', $farm->id)
            ->orderByDesc('period_end')
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $limit = (int) $request->query('limit', 20);
        $rows  = $query->limit($limit)->get();

        $commissionRate = (float) $farm->commission_rate;

        $data = $rows->map(fn ($p) => $this->formatPayout($p, $commissionRate, $farm))->all();

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * GET /farm/payouts/{id} — chi tiết một đợt thanh toán + danh sách đơn đóng góp.
     *
     * Header (gross/phí/net/kg) lấy từ row payout đã chốt. Phần "orders" liệt kê
     * từng đơn trong kỳ (đơn delivering/delivered, gom theo order_id) để farm đối
     * soát: mỗi đơn bao nhiêu kg, doanh thu gộp bao nhiêu. Với payout draft (đang
     * tích lũy) danh sách này phản ánh trạng thái live tại thời điểm gọi.
     */
    public function payoutDetail(Request $request, int $id): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $payout = \App\Models\FarmPayout::where('farm_id', $farm->id)->find($id);
        if (! $payout) {
            return response()->json(['error' => true, 'message' => 'Không tìm thấy đợt thanh toán'], 404);
        }

        $commissionRate = (float) $farm->commission_rate;

        // Đơn đóng góp trong kỳ: order_items của farm thuộc đơn delivering/delivered,
        // created_at trong [period_start, period_end]. Gom theo order_id.
        $orders = [];
        if ($payout->period_start && $payout->period_end) {
            $rows = \Illuminate\Support\Facades\DB::table('zalo_order_items as oi')
                ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
                ->where('oi.farm_id', $farm->id)
                ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(o.created_at)'), [
                    $payout->period_start->toDateString(),
                    $payout->period_end->toDateString(),
                ])
                ->whereIn('o.status', ['delivering', 'delivered'])
                ->selectRaw('
                    o.id AS order_id,
                    o.created_at AS order_created_at,
                    o.status AS order_status,
                    COALESCE(SUM(oi.quantity), 0) AS qty,
                    COALESCE(SUM(oi.cost_price_snapshot * oi.quantity), 0) AS gross
                ')
                ->groupBy('o.id', 'o.created_at', 'o.status')
                ->orderByDesc('o.created_at')
                ->get();

            $orders = $rows->map(fn ($r) => [
                'order_id'         => (int) $r->order_id,
                'order_created_at' => $r->order_created_at,
                'order_status'     => (string) $r->order_status,
                'qty'              => round((float) $r->qty, 2),
                'gross'            => round((float) $r->gross, 2),
            ])->all();
        }

        return response()->json([
            'error' => false,
            'data'  => [
                'payout' => $this->formatPayout($payout, $commissionRate, $farm),
                'orders' => $orders,
            ],
        ]);
    }

    /**
     * Chuẩn hoá 1 row FarmPayout cho FE, bổ sung breakdown phí hoa hồng và ngày
     * dự kiến trả.
     *
     * commission_amount = gross * (1 - commission_rate)  → phí Vietponics giữ.
     * net_estimated      = gross * commission_rate + adjustment → khớp công thức
     *   admin dùng khi chốt lệnh chi (FarmPayoutController@store). Trả kèm để FE
     *   hiển thị nhất quán kể cả khi row draft chưa áp phí vào net_payout.
     */
    private function formatPayout($p, float $commissionRate, Farm $farm): array
    {
        $gross      = (float) $p->gross_revenue;
        $adjustment = (float) $p->adjustment;
        $commission = round($gross * (1 - $commissionRate), 2);
        $netEst     = round($gross * $commissionRate + $adjustment, 2);

        return [
            'id'                => (int) $p->id,
            'period_start'      => optional($p->period_start)->toDateString(),
            'period_end'        => optional($p->period_end)->toDateString(),
            'total_sold'        => (float) $p->total_sold,
            'gross_revenue'     => $gross,
            'commission_rate'   => $commissionRate,
            'commission_amount' => $commission,
            'adjustment'        => $adjustment,
            'net_payout'        => (float) $p->net_payout,
            'net_estimated'     => $netEst,
            'status'            => $p->status,
            'expected_pay_date' => $this->expectedPayDate($p, $farm),
            'paid_at'           => optional($p->paid_at)->toIso8601String(),
            'payment_method'    => $p->payment_method,
            'transaction_ref'   => $p->transaction_ref,
            'note'              => $p->note,
        ];
    }

    /**
     * Ngày dự kiến trả cho một payout chưa trả: ngày làm việc đầu tiên SAU khi
     * kết thúc kỳ. Quy ước hiện tại: period_end + 1 ngày (vd kỳ kết thúc CN 19/05
     * → dự kiến trả T2 20/05). Trả null cho payout đã paid/cancelled.
     */
    private function expectedPayDate($p, Farm $farm): ?string
    {
        if (in_array($p->status, ['paid', 'cancelled'], true) || ! $p->period_end) {
            return null;
        }
        return $p->period_end->copy()->addDay()->toDateString();
    }
}
