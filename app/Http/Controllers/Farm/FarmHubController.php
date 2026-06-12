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
                // Cờ farm hiện tại là "bộ phận đóng gói" (Package Hub) — FE dùng để
                // bật/tắt toàn bộ UI thao tác đơn. Farm thường → chỉ xem chỉ-đọc.
                'is_packing_hub' => (bool) $farm->is_packing_hub,
                // Vai trò người đang đăng nhập trong farm — FE dùng để bật/tắt UI
                // chỉ-owner (vd nút "Phân công") và xác định đơn của-mình.
                'viewer'         => [
                    'customer_id'    => (int) $customer->id,
                    'name'           => $customer->name ?: 'Thành viên',
                    'farm_role'      => $customer->farm_role, // 'owner' | 'staff'
                    'is_owner'       => $customer->isFarmOwner(),
                    // Lặp lại ở cấp viewer để FE đọc gọn cùng chỗ với is_owner.
                    'is_packing_hub' => (bool) $farm->is_packing_hub,
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
        // Dùng chung cho cả 2 nhóm (đặt/giao) — "nhập hôm nay" không phụ thuộc basis.
        $stockedToday = \Illuminate\Support\Facades\DB::table('farm_stock_batches')
            ->where('farm_id', $farm->id)
            ->whereDate('batch_date', $today)
            ->selectRaw('product_id, SUM(quantity_in) AS stocked')
            ->groupBy('product_id')
            ->pluck('stocked', 'product_id');

        // 2. Remaining: tổng quantity_remaining của tất cả active batch (không
        // giới hạn ngày). Đây là "còn bao nhiêu để bán tiếp" — không phải tồn
        // riêng của batch hôm nay. Dùng chung cho cả 2 nhóm.
        $remainingRows = \Illuminate\Support\Facades\DB::table('farm_stock_batches')
            ->where('farm_id', $farm->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->selectRaw('product_id, SUM(quantity_remaining) AS remaining')
            ->groupBy('product_id')
            ->pluck('remaining', 'product_id');

        // 3. HUB-01: hai nhóm bán hôm nay theo 2 basis tách bạch, mỗi nhóm tự
        // nhất quán với card overview cùng tab.
        //   - placed:    đơn ĐÃ ĐẶT (mọi status trừ 'cancelled', lọc created_at).
        //   - delivered: đơn ĐÃ GIAO (status='delivered', lọc delivered_at).
        $soldPlaced = $this->soldByProductForBasis(
            $farm->id,
            fn ($q) => $q->where('o.status', '!=', 'cancelled')
                        ->whereBetween('o.created_at', [$todayStartUtc, $todayEndUtc]),
        );
        $soldDelivered = $this->soldByProductForBasis(
            $farm->id,
            fn ($q) => $q->where('o.status', 'delivered')
                        ->whereNotNull('o.delivered_at')
                        ->whereBetween('o.delivered_at', [$todayStartUtc, $todayEndUtc]),
        );

        // 4. Tên sản phẩm: 1 query cho union mọi product xuất hiện ở cả 2 nhóm.
        $allProductIds = array_unique(array_merge(
            array_keys($stockedToday->toArray()),
            array_keys($soldPlaced),
            array_keys($soldDelivered),
        ));
        $names = empty($allProductIds)
            ? collect()
            : \Illuminate\Support\Facades\DB::table('zalo_products')
                ->whereIn('id', $allProductIds)
                ->pluck('name', 'id');

        // 5. Build rows cho từng nhóm. stocked/remaining dùng chung; sold/revenue
        // theo basis. Union product mỗi nhóm = stockedToday ∪ soldOfThatBasis.
        $productsPlaced    = $this->buildProductRows($stockedToday, $soldPlaced, $remainingRows, $names);
        $productsDelivered = $this->buildProductRows($stockedToday, $soldDelivered, $remainingRows, $names);

        // 6. AI hint tính trên nhóm "đã đặt" (nhịp bán trong ngày, gồm đơn mới —
        // hợp với gợi ý restock/flash-sale).
        $hint = $this->buildAiHint($productsPlaced, $vnNow->hour);

        return response()->json([
            'error' => false,
            'data'  => [
                'products_placed'    => $productsPlaced,
                'products_delivered' => $productsDelivered,
                'hint'               => $hint,
            ],
        ]);
    }

    /**
     * Tổng sold/revenue theo product cho 1 basis (đặt/giao). $scope nhận query
     * builder (alias oi/o đã join) và áp filter status + thời gian tương ứng.
     *
     * @return array<int, array{sold: float, revenue: float}>
     */
    private function soldByProductForBasis(int $farmId, callable $scope): array
    {
        $query = \Illuminate\Support\Facades\DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.farm_id', $farmId);

        $scope($query);

        $rows = $query
            ->selectRaw('
                oi.product_id AS product_id,
                COALESCE(SUM(oi.quantity), 0) AS sold,
                COALESCE(SUM(oi.price * oi.quantity), 0) AS revenue
            ')
            ->groupBy('oi.product_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->product_id] = [
                'sold'    => (float) $r->sold,
                'revenue' => (float) $r->revenue,
            ];
        }
        return $out;
    }

    /**
     * Dựng danh sách row "Sản phẩm hôm nay" cho 1 nhóm. Product hiển thị =
     * union(stockedToday, soldByProduct). stocked/remaining dùng chung 2 nhóm;
     * sold/revenue/sellthrough/status theo basis của $soldByProduct.
     *
     * @param  array<int, array{sold: float, revenue: float}> $soldByProduct
     */
    private function buildProductRows($stockedToday, array $soldByProduct, $remainingRows, $names): array
    {
        $productIds = array_unique(array_merge(
            array_keys($stockedToday->toArray()),
            array_keys($soldByProduct),
        ));

        if (empty($productIds)) {
            return [];
        }

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

        return $rows;
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
     * GET /farm/orders/incoming — đơn đang chờ giao, theo MÔ HÌNH PACKAGE HUB.
     *
     * Hai chế độ tuỳ farm đang đăng nhập có phải Package Hub không:
     *
     *   - Hub: thấy MỌI đơn đang xử lý (mọi item của mọi farm), đính phiếu đóng
     *     gói của hub (1 phiếu/đơn). Hub đóng gói cả đơn → có quyền thao tác.
     *
     *   - Farm thường: chỉ thấy đơn CÓ HÀNG của mình (item farm_id = farm này),
     *     trả kèm read_only=true và assignment để hiển thị tham khảo. KHÔNG có
     *     quyền thao tác — FE ẩn mọi nút.
     *
     * Mỗi row là một order_item; đơn nhiều item xuất hiện nhiều row (FE group
     * theo order_id). Thông tin KH che server-side cho mọi vai trò.
     *
     * Lấy đơn ở pending/confirmed/preparing/delivering (chưa delivered/cancelled).
     * Limit 200 row mới nhất, sort theo zalo_orders.created_at desc.
     */
    public function incomingOrders(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');
        /** @var \App\Models\Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $isHub = (bool) $farm->is_packing_hub;

        // Phiếu đóng gói luôn thuộc Package Hub. Đảm bảo mọi đơn đang xử lý có
        // đúng 1 phiếu hub (sinh lazily nếu chưa có). No-op khi chưa cấu hình hub.
        $this->ensureAssignmentsExist();
        $hub = Farm::primaryPackingHub();

        $query = \Illuminate\Support\Facades\DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('zalo_deliveries as d', 'd.order_id', '=', 'o.id')
            // Phiếu đóng gói gắn theo HUB (1 phiếu/đơn), không theo farm sở hữu hàng.
            ->leftJoin('order_farm_assignments as a', function ($join) use ($hub) {
                $join->on('a.order_id', '=', 'oi.order_id');
                if ($hub) {
                    $join->where('a.farm_id', '=', $hub->id);
                } else {
                    // Chưa có hub → không khớp phiếu nào (assignment toàn null).
                    $join->whereRaw('1 = 0');
                }
            })
            // Tên người đang đóng gói — để FE hiện "Đang đóng: NV. Tuấn".
            ->leftJoin('customers as pc', 'pc.id', '=', 'a.assigned_customer_id')
            ->whereIn('o.status', ['pending', 'confirmed', 'preparing', 'delivering']);

        // Farm thường: chỉ đơn CÓ ÍT NHẤT 1 item của farm này. Hub: mọi đơn.
        if (! $isHub) {
            $query->whereExists(function ($q) use ($farm) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('zalo_order_items as mine')
                  ->whereColumn('mine.order_id', 'oi.order_id')
                  ->where('mine.farm_id', $farm->id);
            });
            // Farm thường chỉ NHÌN phần hàng của mình trong đơn — không thấy item
            // farm khác (giữ ranh giới dữ liệu giữa các farm).
            $query->where('oi.farm_id', $farm->id);
        }

        $rows = $query
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

        // FE quyết định nút theo assignment_status + is_mine (chỉ hub mới thao
        // tác). read_only=true cho farm thường → FE ẩn mọi nút. Thông tin KH che
        // server-side cho mọi vai trò.
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
                // Thông tin KH đã che server-side — không vai trò nào nhận bản đầy đủ.
                'customer_name'       => $r->customer_name,
                'customer_phone'      => \App\Support\ContactMasker::maskPhone($r->customer_phone),
                'delivery_address'    => \App\Support\ContactMasker::maskAddress($r->delivery_address),
                // Trạng thái đóng gói + ai đang/đã đóng + mốc thời gian (của phiếu hub).
                'assignment_status'   => $r->assignment_status ?? \App\Models\OrderFarmAssignment::STATUS_UNASSIGNED,
                'assigned_customer_id'=> $r->assigned_customer_id !== null ? (int) $r->assigned_customer_id : null,
                'assigned_customer_name' => $r->assigned_customer_name,
                'packing_started_at'  => $r->packing_started_at,
                'packed_at'           => $r->packed_at,
                // is_mine chỉ có nghĩa cho hub (người đóng gói); farm thường luôn false.
                'is_mine'             => $isHub && $r->assigned_customer_id !== null
                    && (int) $r->assigned_customer_id === (int) $customer->id,
                // Farm thường = xem chỉ-đọc, không có quyền thao tác đơn.
                'read_only'           => ! $isHub,
            ])
            ->values()
            ->all();

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * GET /farm/staff — danh sách thành viên Package Hub có thể được gán đóng
     * gói (owner + staff của HUB). Dùng cho picker "Phân công" của chủ hub trên
     * Mini App. Người đóng gói luôn thuộc hub, nên list lấy theo hub farm chứ
     * không theo farm đang đăng nhập. Trả rỗng nếu chưa cấu hình hub.
     */
    public function staff(Request $request): JsonResponse
    {
        $hub = Farm::primaryPackingHub();
        if (! $hub) {
            return response()->json(['error' => false, 'data' => []]);
        }

        $members = \App\Models\Customer::where('farm_id', $hub->id)
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
     * Tạo phiếu đóng gói 'unassigned' (gắn Package Hub) cho mọi đơn đang xử lý
     * mà chưa có phiếu hub. Idempotent — chạy mỗi lần load incoming để bắt kịp
     * đơn mới. No-op nếu chưa cấu hình Package Hub nào (khâu đóng gói "tắt").
     *
     * Đơn vị phiếu = (order, hub) — 1 phiếu/đơn, KHÔNG theo farm sở hữu hàng.
     */
    private function ensureAssignmentsExist(): void
    {
        $hub = Farm::primaryPackingHub();
        if (! $hub) {
            return; // Chưa có hub → không sinh phiếu.
        }

        $missing = \Illuminate\Support\Facades\DB::table('zalo_orders as o')
            ->leftJoin('order_farm_assignments as a', function ($join) use ($hub) {
                $join->on('a.order_id', '=', 'o.id')
                     ->where('a.farm_id', '=', $hub->id);
            })
            ->whereIn('o.status', ['pending', 'confirmed', 'preparing', 'delivering'])
            // Chỉ đơn thực sự có hàng (có order_item) — tránh tạo phiếu rác.
            ->whereExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
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
            'status'     => \App\Models\OrderFarmAssignment::STATUS_UNASSIGNED,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // insertOrIgnore tránh race khi 2 request cùng tạo (unique order_id+farm_id).
        \Illuminate\Support\Facades\DB::table('order_farm_assignments')->insertOrIgnore($rows);
    }

    /**
     * Gate chỉ-owner cho mục tài chính (payout). Trả JsonResponse 403 nếu người
     * gọi không phải owner của farm; null nếu hợp lệ. zalo_customer đã được
     * EnsureFarmPartner load từ DB và gắn vào request — không tra DB lại.
     *
     * Đặt ở ĐẦU method payout (trước validate / truy vấn) để staff bị chặn sớm,
     * không leak cả sự tồn tại của payout (staff farm khác cũng nhận 403, không
     * phải 404). Khác với scope-theo-farm (404) áp cho owner farm khác.
     */
    private function ensureOwner(Request $request): ?JsonResponse
    {
        /** @var \App\Models\Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        if (! $customer->isFarmOwner()) {
            return response()->json([
                'error'   => true,
                'message' => 'Bạn không có quyền xem mục này',
            ], 403);
        }

        return null;
    }

    /**
     * GET /farm/payouts — danh sách đợt thanh toán của farm.
     *
     * Sort theo period_end desc (đợt mới nhất trên cùng). Trả mọi status
     * (draft/pending/paid/cancelled) để farm thấy đầy đủ lịch sử + đợt hiện
     * tại (draft, do cron snapshot daily tích lũy).
     *
     * Limit mặc định 20, optional ?status=draft|pending|paid|cancelled để filter.
     *
     * Chỉ owner — staff không xem dữ liệu tài chính (gross/phí/net).
     */
    public function payouts(Request $request): JsonResponse
    {
        if ($resp = $this->ensureOwner($request)) {
            return $resp;
        }

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
        if ($resp = $this->ensureOwner($request)) {
            return $resp;
        }

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
