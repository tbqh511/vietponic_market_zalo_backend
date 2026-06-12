<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloOrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * FarmDashboardService — nguồn dữ liệu cho Farm Partner Hub.
 *
 * Mọi báo cáo doanh thu/đơn hàng đều áp 2 ràng buộc bất di:
 *   1. zalo_order_items.farm_id = farm hiện tại (đã được phân bổ FEFO).
 *   2. zalo_orders.status = 'delivered' AND delivered_at BETWEEN range.
 *
 * KHÔNG đếm đơn pending/confirmed/preparing/delivering vì có thể bị huỷ.
 * KHÔNG dựa vào payment_status — COD có thể delivered nhưng payment_status
 * vẫn 'pending' cho tới khi tài xế nộp tiền, dashboard farm cần phản ánh
 * đơn đã giao xong (revenue recognition policy đã chốt với user).
 *
 * Range bucket: 'today' | '7d' | '30d' | 'custom' (kèm from/to).
 * Toàn bộ tính theo timezone Asia/Ho_Chi_Minh (UTC+7), bao gồm cả ngày hôm nay.
 * range được resolve thành [Carbon $from, Carbon $to] (đều ở VN tz, $to = now()).
 *
 * Hiệu năng: mọi query đều có index hỗ trợ — zalo_orders(status, delivered_at)
 * và zalo_order_items(farm_id). Không eager-load toàn bộ collection rồi sum
 * trong PHP để tránh OOM khi farm bán nhiều.
 */
class FarmDashboardService
{
    /**
     * Tổng quan: doanh thu, số đơn, giá trị đơn trung bình, top product hôm nay.
     *
     * @return array{
     *   range: array{from: string, to: string, key: string},
     *   revenue: float,
     *   cost: float,
     *   profit: float,
     *   orders_count: int,
     *   items_sold: float,
     *   avg_order_value: float,
     *   top_product: ?array{product_id: int, name: string, qty: float, revenue: float}
     * }
     */
    public function getOverview(Farm $farm, string $range = '7d', ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->resolveRange($range, $from, $to);

        // HUB-01: hai chỉ số tách basis, mỗi cái tự nhất quán (card + list cùng nguồn):
        //   - delivered = đơn ĐÃ GIAO (status='delivered', lọc delivered_at) — basis
        //     doanh thu lịch sử, dùng chung cho analytics/timeseries/payout.
        //   - placed    = đơn ĐÃ ĐẶT (mọi status trừ 'cancelled', lọc created_at) —
        //     phản ánh đơn farm nhận trong ngày, gồm cả đơn chưa giao.
        $delivered = $this->aggregateMetrics($this->itemsBaseQuery($farm, $start, $end));
        $placed    = $this->aggregateMetrics($this->placedBaseQuery($farm, $start, $end));

        // Lấy top 1 product (basis "đã giao") để hiển thị card "bán chạy nhất".
        $top = $this->getTopProducts($farm, $range, 1, $from, $to);

        // Field cũ ở top-level GIỮ NGUYÊN (= basis "đã giao") cho backward-compat
        // với caller khác (analytics). Hai khối placed/delivered bổ sung cho FE
        // dashboard đọc gọn theo từng tab.
        return [
            'range' => [
                'from' => $start->toIso8601String(),
                'to'   => $end->toIso8601String(),
                'key'  => $range,
            ],
            'revenue'         => $delivered['revenue'],
            'cost'            => $delivered['cost'],
            'profit'          => $delivered['profit'],
            'orders_count'    => $delivered['orders_count'],
            'items_sold'      => $delivered['items_sold'],
            'avg_order_value' => $delivered['avg_order_value'],
            'top_product'     => $top[0] ?? null,
            'placed'          => $placed,
            'delivered'       => $delivered,
        ];
    }

    /**
     * Aggregate doanh thu/cost/đơn/sản lượng trên 1 base query (delivered|placed).
     * Tách ra để 2 basis HUB-01 dùng chung, tránh lặp SQL.
     *
     * @return array{revenue: float, cost: float, profit: float, orders_count: int, items_sold: float, avg_order_value: float}
     */
    protected function aggregateMetrics($baseQuery): array
    {
        // Dùng SUM(price*qty) thay vì zalo_orders.total vì 1 order có thể split
        // nhiều farm — mỗi farm chỉ lấy doanh thu phần items của mình.
        $agg = $baseQuery
            ->selectRaw('
                COALESCE(SUM(zalo_order_items.price * zalo_order_items.quantity), 0) AS revenue,
                COALESCE(SUM(COALESCE(zalo_order_items.cost_price_snapshot, 0) * zalo_order_items.quantity), 0) AS cost,
                COALESCE(SUM(zalo_order_items.quantity), 0) AS items_sold,
                COUNT(DISTINCT zalo_order_items.order_id) AS orders_count
            ')
            ->first();

        $revenue     = (float) ($agg->revenue ?? 0);
        $cost        = (float) ($agg->cost ?? 0);
        $ordersCount = (int) ($agg->orders_count ?? 0);
        $itemsSold   = (float) ($agg->items_sold ?? 0);

        return [
            'revenue'         => round($revenue, 2),
            'cost'            => round($cost, 2),
            'profit'          => round($revenue - $cost, 2),
            'orders_count'    => $ordersCount,
            'items_sold'      => round($itemsSold, 2),
            // Avg order value chỉ có nghĩa khi có đơn. Tránh chia 0.
            'avg_order_value' => $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0.0,
        ];
    }

    /**
     * Series doanh thu theo bucket (day/week) — dùng cho chart trên dashboard.
     *
     * bucket='day':  GROUP BY DATE(delivered_at, VN tz)
     * bucket='week': GROUP BY YEARWEEK(delivered_at, mode=3) — ISO week, Mon-Sun
     *
     * Trả về mảng dày đặc (fill 0 cho ngày không có đơn) để frontend chart không
     * bị lệch trục x. Caller cần biết bucket để render label.
     *
     * @return array<int, array{bucket: string, revenue: float, orders: int, items: float}>
     */
    public function getRevenueTimeseries(Farm $farm, string $range = '7d', string $bucket = 'day', ?string $from = null, ?string $to = null): array
    {
        if (!in_array($bucket, ['day', 'week'], true)) {
            throw new InvalidArgumentException("bucket must be 'day' or 'week', got: {$bucket}");
        }

        [$start, $end] = $this->resolveRange($range, $from, $to);

        // delivered_at lưu theo GIỜ VN (xem ghi chú TZ ở itemsBaseQuery) → bucket
        // trực tiếp trên cột, KHÔNG CONVERT_TZ (trước đây convert giả định lưu
        // UTC làm lệch ngày). Bucket key 'YYYY-MM-DD' (day) hoặc 'YYYY-Www'
        // (week — ISO 8601, vd '2026-W21').
        $bucketExpr = $bucket === 'day'
            ? "DATE(zalo_orders.delivered_at)"
            : "DATE_FORMAT(zalo_orders.delivered_at, '%x-W%v')";

        $rows = $this->itemsBaseQuery($farm, $start, $end)
            ->selectRaw("
                {$bucketExpr} AS bucket_key,
                COALESCE(SUM(zalo_order_items.price * zalo_order_items.quantity), 0) AS revenue,
                COUNT(DISTINCT zalo_order_items.order_id) AS orders,
                COALESCE(SUM(zalo_order_items.quantity), 0) AS items
            ")
            ->groupBy('bucket_key')
            ->orderBy('bucket_key', 'asc')
            ->get()
            ->keyBy('bucket_key');

        // Fill dày đặc: với day, sinh đủ các ngày trong range; với week,
        // sinh từng ISO week. Frontend không cần biết ngày nào trống.
        $series = [];
        $cursor = $start->copy();

        if ($bucket === 'day') {
            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m-d');
                $row = $rows->get($key);
                $series[] = [
                    'bucket'  => $key,
                    'revenue' => $row ? round((float) $row->revenue, 2) : 0.0,
                    'orders'  => $row ? (int) $row->orders : 0,
                    'items'   => $row ? round((float) $row->items, 2) : 0.0,
                ];
                $cursor->addDay();
            }
        } else {
            // ISO week: %x = ISO year (gắn với week), %v = ISO week (01..53).
            // Carbon::isoFormat('GGGG-[W]WW') trả format tương đương.
            while ($cursor->lte($end)) {
                $key = $cursor->isoFormat('GGGG-[W]WW');
                if (!isset($series[$key])) {
                    $row = $rows->get($key);
                    $series[$key] = [
                        'bucket'  => $key,
                        'revenue' => $row ? round((float) $row->revenue, 2) : 0.0,
                        'orders'  => $row ? (int) $row->orders : 0,
                        'items'   => $row ? round((float) $row->items, 2) : 0.0,
                    ];
                }
                $cursor->addDay();
            }
            $series = array_values($series);
        }

        return $series;
    }

    /**
     * Top sản phẩm bán chạy của farm trong range. Sắp xếp theo revenue giảm dần.
     *
     * @return array<int, array{
     *   product_id: int, name: string, qty: float,
     *   revenue: float, cost: float, profit: float, orders_count: int
     * }>
     */
    public function getTopProducts(Farm $farm, string $range = '30d', int $limit = 10, ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->resolveRange($range, $from, $to);

        $limit = max(1, min($limit, 100));

        // Join sang zalo_products lấy name. Lưu ý: nếu product bị xoá thì
        // LEFT JOIN sẽ trả name=null — giữ row vẫn có ích cho farm xem doanh
        // thu lịch sử (đừng INNER JOIN).
        $rows = $this->itemsBaseQuery($farm, $start, $end)
            ->leftJoin('zalo_products', 'zalo_products.id', '=', 'zalo_order_items.product_id')
            ->selectRaw("
                zalo_order_items.product_id AS product_id,
                COALESCE(zalo_products.name, zalo_order_items.name) AS name,
                COALESCE(SUM(zalo_order_items.quantity), 0) AS qty,
                COALESCE(SUM(zalo_order_items.price * zalo_order_items.quantity), 0) AS revenue,
                COALESCE(SUM(COALESCE(zalo_order_items.cost_price_snapshot, 0) * zalo_order_items.quantity), 0) AS cost,
                COUNT(DISTINCT zalo_order_items.order_id) AS orders_count
            ")
            ->groupBy('zalo_order_items.product_id', 'zalo_products.name', 'zalo_order_items.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            $revenue = (float) $r->revenue;
            $cost    = (float) $r->cost;
            return [
                'product_id'   => (int) $r->product_id,
                'name'         => (string) ($r->name ?? ''),
                'qty'          => round((float) $r->qty, 2),
                'revenue'      => round($revenue, 2),
                'cost'         => round($cost, 2),
                'profit'       => round($revenue - $cost, 2),
                'orders_count' => (int) $r->orders_count,
            ];
        })->all();
    }

    /**
     * Tồn kho hiện tại của farm: tổng quan theo SKU + danh sách batch sắp hết hạn.
     *
     * Không phụ thuộc range thời gian — luôn là snapshot "ngay bây giờ".
     *
     * Trả về:
     *   skus: [{product_id, name, total_remaining, batches_count, earliest_expire}]
     *   expiring_soon: batches có expire_date <= now+7d (cảnh báo)
     *   low_stock: SKU có total_remaining <= 5 (ngưỡng cứng, có thể cấu hình sau)
     *   totals: tổng tồn (qty), tổng số batch active
     *
     * @return array{
     *   skus: array<int, array{product_id: int, name: string, total_remaining: float, batches_count: int, earliest_expire: ?string}>,
     *   expiring_soon: array<int, array{batch_id: int, product_id: int, name: string, quantity_remaining: float, expire_date: string, days_left: int}>,
     *   low_stock: array<int, array{product_id: int, name: string, total_remaining: float}>,
     *   totals: array{total_remaining: float, batches_count: int}
     * }
     */
    public function getInventorySummary(Farm $farm): array
    {
        // Tổng hợp theo product. Dùng batch.status='active' AND quantity_remaining>0
        // — KHÔNG dùng scopeActive() của FarmStockBatch để có thể join thêm
        // zalo_products lấy tên trong cùng query.
        $skuRows = DB::table('farm_stock_batches')
            ->leftJoin('zalo_products', 'zalo_products.id', '=', 'farm_stock_batches.product_id')
            ->where('farm_stock_batches.farm_id', $farm->id)
            ->where('farm_stock_batches.status', 'active')
            ->where('farm_stock_batches.quantity_remaining', '>', 0)
            ->selectRaw("
                farm_stock_batches.product_id AS product_id,
                COALESCE(zalo_products.name, '') AS name,
                COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) AS total_remaining,
                COUNT(*) AS batches_count,
                MIN(farm_stock_batches.expire_date) AS earliest_expire
            ")
            ->groupBy('farm_stock_batches.product_id', 'zalo_products.name')
            ->orderByDesc('total_remaining')
            ->get();

        $skus = $skuRows->map(fn ($r) => [
            'product_id'      => (int) $r->product_id,
            'name'            => (string) $r->name,
            'total_remaining' => round((float) $r->total_remaining, 2),
            'batches_count'   => (int) $r->batches_count,
            'earliest_expire' => $r->earliest_expire,
        ])->all();

        // Batch sắp hết hạn trong 7 ngày tới — cảnh báo cho farm xử lý sớm.
        // Không bao gồm batch không có expire_date.
        $vnNow = Carbon::now('Asia/Ho_Chi_Minh')->startOfDay();
        $vnSoon = $vnNow->copy()->addDays(7);

        $expiringRows = FarmStockBatch::query()
            ->leftJoin('zalo_products', 'zalo_products.id', '=', 'farm_stock_batches.product_id')
            ->where('farm_stock_batches.farm_id', $farm->id)
            ->where('farm_stock_batches.status', 'active')
            ->where('farm_stock_batches.quantity_remaining', '>', 0)
            ->whereNotNull('farm_stock_batches.expire_date')
            ->whereBetween('farm_stock_batches.expire_date', [
                $vnNow->toDateString(),
                $vnSoon->toDateString(),
            ])
            ->orderBy('farm_stock_batches.expire_date', 'asc')
            ->selectRaw('
                farm_stock_batches.id AS batch_id,
                farm_stock_batches.product_id AS product_id,
                COALESCE(zalo_products.name, \'\') AS name,
                farm_stock_batches.quantity_remaining AS quantity_remaining,
                farm_stock_batches.expire_date AS expire_date
            ')
            ->get();

        $expiringSoon = $expiringRows->map(function ($r) use ($vnNow) {
            $expDate = Carbon::parse($r->expire_date, 'Asia/Ho_Chi_Minh')->startOfDay();
            return [
                'batch_id'           => (int) $r->batch_id,
                'product_id'         => (int) $r->product_id,
                'name'               => (string) $r->name,
                'quantity_remaining' => round((float) $r->quantity_remaining, 2),
                'expire_date'        => $expDate->toDateString(),
                // diffInDays âm khi đã quá hạn (batch hết hạn nhưng chưa
                // được cron đánh dấu expired); frontend hiển thị tương ứng.
                'days_left'          => (int) $vnNow->diffInDays($expDate, false),
            ];
        })->all();

        // Low stock: SKU có total_remaining <= 5. Ngưỡng cứng tạm thời; nếu sau
        // này farm cần config riêng từng SKU thì chuyển sang field trên farm_product.
        $lowStock = array_values(array_filter($skus, fn ($s) => $s['total_remaining'] <= 5));

        $totalRemaining = array_sum(array_column($skus, 'total_remaining'));
        $batchesCount   = array_sum(array_column($skus, 'batches_count'));

        return [
            'skus'          => $skus,
            'expiring_soon' => $expiringSoon,
            'low_stock'     => $lowStock,
            'totals'        => [
                'total_remaining' => round($totalRemaining, 2),
                'batches_count'   => $batchesCount,
            ],
        ];
    }

    /**
     * Query builder chung cho mọi báo cáo doanh thu farm.
     *   - JOIN zalo_orders để áp filter status='delivered' + delivered_at range
     *   - WHERE farm_id = farm hiện tại
     *
     * $start/$end ở VN tz và được so trực tiếp với delivered_at — mốc thời gian
     * trong DB cũng lưu theo GIỜ VN (app.timezone=Asia/Ho_Chi_Minh, cột dateTime
     * naive, delivered_at = now()). KHÔNG convert sang UTC: trước đây có
     * setTimezone('UTC') khiến cửa sổ "hôm nay" lệch -7h, đơn giao sau ~07:00 sáng
     * VN bị rớt (HUB-01 TZ-fix B18).
     */
    protected function itemsBaseQuery(Farm $farm, Carbon $start, Carbon $end)
    {
        return ZaloOrderItem::query()
            ->join('zalo_orders', 'zalo_orders.id', '=', 'zalo_order_items.order_id')
            ->where('zalo_order_items.farm_id', $farm->id)
            ->where('zalo_orders.status', 'delivered')
            ->whereNotNull('zalo_orders.delivered_at')
            ->whereBetween('zalo_orders.delivered_at', [$start, $end]);
    }

    /**
     * Query builder cho chỉ số "ĐÃ ĐẶT" (HUB-01):
     *   - JOIN zalo_orders, WHERE farm_id = farm hiện tại
     *   - status != 'cancelled' (mọi đơn còn hiệu lực)
     *   - lọc theo created_at (thời điểm đặt) trong range
     *
     * Khác deliveredBaseQuery ở: KHÔNG lọc status='delivered', dùng created_at
     * thay delivered_at → gồm cả đơn vừa đặt chưa giao. So $start/$end (giờ VN)
     * trực tiếp với created_at (cũng lưu giờ VN) — xem ghi chú TZ ở itemsBaseQuery.
     */
    protected function placedBaseQuery(Farm $farm, Carbon $start, Carbon $end)
    {
        return ZaloOrderItem::query()
            ->join('zalo_orders', 'zalo_orders.id', '=', 'zalo_order_items.order_id')
            ->where('zalo_order_items.farm_id', $farm->id)
            ->where('zalo_orders.status', '!=', 'cancelled')
            ->whereBetween('zalo_orders.created_at', [$start, $end]);
    }

    /**
     * Resolve range key → [Carbon $from, Carbon $to] (cả hai ở Asia/Ho_Chi_Minh).
     *
     * 'today' = [00:00 hôm nay, now]
     * '7d'    = [00:00 ngày (today-6), now]   — 7 ngày bao gồm hôm nay
     * '30d'   = [00:00 ngày (today-29), now]  — 30 ngày bao gồm hôm nay
     * 'custom'= [00:00 from, 23:59:59 to]     — bắt buộc truyền $from, $to (YYYY-MM-DD)
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(string $range, ?string $from = null, ?string $to = null): array
    {
        $tz  = 'Asia/Ho_Chi_Minh';
        $now = Carbon::now($tz);

        switch ($range) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()];

            case '7d':
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()];

            case '30d':
                return [$now->copy()->subDays(29)->startOfDay(), $now->copy()];

            case 'custom':
                if (!$from || !$to) {
                    throw new InvalidArgumentException("range='custom' requires both from and to (YYYY-MM-DD)");
                }
                $start = Carbon::parse($from, $tz)->startOfDay();
                $end   = Carbon::parse($to, $tz)->endOfDay();
                if ($start->gt($end)) {
                    throw new InvalidArgumentException("from must be <= to");
                }
                // Giới hạn 1 năm để tránh query đè DB.
                if ($start->diffInDays($end) > 366) {
                    throw new InvalidArgumentException("range cannot exceed 366 days");
                }
                return [$start, $end];

            default:
                throw new InvalidArgumentException("Unknown range: {$range}. Expected: today|7d|30d|custom");
        }
    }
}
