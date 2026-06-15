<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FarmStockController — Farm Partner Hub: quản lý batch tồn kho.
 *
 * Thay cho mô hình cũ (1 SKU = 1 dòng stock trên zalo_products), giờ mỗi lần
 * farm nhập rau là một batch (lô) riêng. FE xem được:
 *   - List batch của farm hiện tại (mặc định)
 *   - Aggregate theo SKU (group_by=product) khi cần overview
 *   - Tạo batch mới (import) — tự gắn farm_id từ middleware
 *   - Đóng/huỷ batch (close-batch) — set status='depleted'/'recalled'
 *
 * Middleware zalo.farm đã đính `farm` model vào request attributes, controller
 * tuyệt đối không tra farm theo URL/customer ID nữa (tránh IDOR).
 */
class FarmStockController extends Controller
{
    /**
     * GET /api/farm/inventory
     *   ?view=skus|batches    — default skus (SKU-level, dùng cho mini app inventory list)
     *   ?status=active|depleted|expired|recalled|all   — chỉ áp dụng khi view=batches
     *   ?product_id=...       — chỉ áp dụng khi view=batches
     *   ?q=                   — search SKU name
     *   ?category_id=         — filter theo danh mục (view=skus)
     *   ?stock_status=all|in_stock|low|out — filter tồn kho (view=skus)
     *   ?sort=name|low_first|stock_asc|stock_desc — sắp xếp (view=skus)
     *   ?page=1
     *   ?per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $view = $request->query('view', 'skus') === 'batches' ? 'batches' : 'skus';

        if ($view === 'batches') {
            return $this->indexBatches($request, $farm);
        }
        return $this->indexSkus($request, $farm);
    }

    private function indexBatches(Request $request, Farm $farm): JsonResponse
    {
        $status    = (string) $request->query('status', 'active');
        $productId = $request->query('product_id');
        $perPage   = max(1, min((int) $request->query('per_page', 20), 100));

        $q = FarmStockBatch::query()
            ->with(['product:id,name,image'])
            ->where('farm_id', $farm->id);

        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($productId) {
            $q->where('product_id', (int) $productId);
        }

        $q->fefo();

        $paginator = $q->paginate($perPage);

        $items = collect($paginator->items())->map(fn ($b) => [
            'id'                 => (int) $b->id,
            'product_id'         => (int) $b->product_id,
            'product_name'       => $b->product?->name,
            'product_image'      => $b->product?->image,
            'batch_date'         => optional($b->batch_date)->toDateString(),
            'expire_date'        => optional($b->expire_date)->toDateString(),
            'quantity_in'        => (float) $b->quantity_in,
            'quantity_sold'      => (float) $b->quantity_sold,
            'quantity_remaining' => (float) $b->quantity_remaining,
            'cost_price'         => (float) $b->cost_price,
            'status'             => $b->status,
            'note'               => $b->note,
            'created_at'         => optional($b->created_at)->toIso8601String(),
        ]);

        return response()->json([
            'error' => false,
            'data'  => $items,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'view'         => 'batches',
            ],
        ]);
    }

    /**
     * View aggregate theo SKU — 1 row = 1 sản phẩm farm cung cấp.
     *
     * Response shape match interface InventoryProduct ở frontend
     * (mini app farm inventory page). Bao gồm cả sản phẩm đã gắn farm qua pivot
     * farm_product nhưng CHƯA có batch active nào — stock=0, is_out_of_stock=true.
     *
     * Support filter q / category_id / stock_status / sort + paginate + stats overview.
     */
    private function indexSkus(Request $request, Farm $farm): JsonResponse
    {
        $q           = trim((string) $request->query('q', ''));
        $categoryId  = $request->query('category_id');
        $stockStatus = (string) $request->query('stock_status', 'all');
        $sort        = (string) $request->query('sort', 'name');
        $perPage     = max(1, min((int) $request->query('per_page', 20), 100));
        $lowThr      = StockService::LOW_STOCK_THRESHOLD;

        // Base query: tất cả SP gắn farm qua pivot, LEFT join batch active để vẫn
        // hiện SP chưa nhập kho (stock=0). Categories left-join vì zalo_products
        // cho phép category_id NULL (onDelete set null).
        $base = DB::table('zalo_products')
            ->join('farm_product', function ($j) use ($farm) {
                $j->on('farm_product.product_id', '=', 'zalo_products.id')
                  ->where('farm_product.farm_id', '=', $farm->id);
            })
            ->leftJoin('zalo_categories', 'zalo_categories.id', '=', 'zalo_products.category_id')
            ->leftJoin('farm_stock_batches', function ($j) use ($farm) {
                $j->on('farm_stock_batches.product_id', '=', 'zalo_products.id')
                  ->where('farm_stock_batches.farm_id', '=', $farm->id)
                  ->where('farm_stock_batches.status', '=', 'active')
                  ->where('farm_stock_batches.quantity_remaining', '>', 0);
            })
            ->groupBy(
                'zalo_products.id',
                'zalo_products.name',
                'zalo_products.image',
                'zalo_products.category_id',
                'zalo_categories.name'
            )
            ->selectRaw('
                zalo_products.id AS id,
                zalo_products.name AS name,
                zalo_products.image AS image,
                zalo_products.category_id AS category_id,
                zalo_categories.name AS category_name,
                COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) AS stock
            ');

        if ($q !== '') {
            $base->where('zalo_products.name', 'like', "%{$q}%");
        }
        if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'all') {
            $base->where('zalo_products.category_id', (int) $categoryId);
        }

        // stock_status filter: dùng HAVING vì stock là aggregate
        switch ($stockStatus) {
            case 'in_stock':
                $base->havingRaw('COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) > ?', [$lowThr]);
                break;
            case 'low':
                $base->havingRaw('COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) > 0')
                     ->havingRaw('COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) <= ?', [$lowThr]);
                break;
            case 'out':
                $base->havingRaw('COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) <= 0');
                break;
        }

        switch ($sort) {
            case 'low_first':
                // Hết hàng (0) trước, rồi sắp hết, rồi tăng dần tồn
                $base->orderByRaw('COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) ASC')
                     ->orderBy('zalo_products.name');
                break;
            case 'stock_asc':
                $base->orderByRaw('COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) ASC');
                break;
            case 'stock_desc':
                $base->orderByRaw('COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) DESC');
                break;
            case 'name':
            default:
                $base->orderBy('zalo_products.name');
                break;
        }

        $paginator = $base->paginate($perPage);

        $items = collect($paginator->items())->map(function ($r) use ($lowThr) {
            $stock = (float) $r->stock;
            return [
                'id'              => (int) $r->id,
                'name'            => (string) $r->name,
                'category'        => $r->category_name !== null ? (string) $r->category_name : null,
                'category_id'     => $r->category_id !== null ? (int) $r->category_id : null,
                'image_url'       => $this->resolveImageUrl($r->image),
                'stock'           => $stock,
                'stock_reserved'  => 0.0,
                'stock_available' => $stock,
                'reorder_point'   => $lowThr,
                'is_low_stock'    => $stock > 0 && $stock <= $lowThr,
                'is_out_of_stock' => $stock <= 0,
            ];
        })->all();

        return response()->json([
            'error' => false,
            'data'  => $items,
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'view'         => 'skus',
            ],
            'stats' => $this->statsForFarm($farm, $lowThr),
        ]);
    }

    /**
     * Stats overview: không áp filter q/category/stock_status — luôn là tổng quan farm.
     *   total_products    = số SKU gắn pivot
     *   total_stock       = SUM remaining trên mọi batch active của farm
     *   low_stock_count   = số SKU có 0 < stock <= LOW_STOCK_THRESHOLD
     *   out_of_stock_count = số SKU stock <= 0
     */
    private function statsForFarm(Farm $farm, int $lowThr): array
    {
        $rows = DB::table('zalo_products')
            ->join('farm_product', function ($j) use ($farm) {
                $j->on('farm_product.product_id', '=', 'zalo_products.id')
                  ->where('farm_product.farm_id', '=', $farm->id);
            })
            ->leftJoin('farm_stock_batches', function ($j) use ($farm) {
                $j->on('farm_stock_batches.product_id', '=', 'zalo_products.id')
                  ->where('farm_stock_batches.farm_id', '=', $farm->id)
                  ->where('farm_stock_batches.status', '=', 'active')
                  ->where('farm_stock_batches.quantity_remaining', '>', 0);
            })
            ->groupBy('zalo_products.id')
            ->selectRaw('
                zalo_products.id AS id,
                COALESCE(SUM(farm_stock_batches.quantity_remaining), 0) AS stock
            ')
            ->get();

        $total       = $rows->count();
        $totalStock  = (float) $rows->sum('stock');
        $lowCount    = $rows->filter(fn ($r) => $r->stock > 0 && $r->stock <= $lowThr)->count();
        $outCount    = $rows->filter(fn ($r) => $r->stock <= 0)->count();

        return [
            'total_products'     => $total,
            'low_stock_count'    => $lowCount,
            'out_of_stock_count' => $outCount,
            'total_stock'        => $totalStock,
        ];
    }

    /**
     * Resolve image URL — tái sử dụng logic của ZaloProduct::getImageUrlAttribute()
     * nhưng nhận trực tiếp raw image string (vì query builder không hydrate model).
     */
    private function resolveImageUrl(?string $image): string
    {
        if (!$image) {
            return rtrim((string) config('app.url'), '/') . '/images/no-image.png';
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($image, '/');
    }

    /**
     * GET /api/farm/inventory/{batchId}/movements
     * Lịch sử order_items đã trừ vào batch này.
     */
    public function movements(Request $request, int $id): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $batch = FarmStockBatch::where('id', $id)
            ->where('farm_id', $farm->id)
            ->first();
        if (!$batch) {
            return response()->json(['error' => true, 'message' => 'Batch không tồn tại'], 404);
        }

        $movements = $batch->orderItems()
            ->with(['order:id,status,created_at,delivered_at'])
            ->orderBy('id', 'desc')
            ->paginate(50);

        $items = collect($movements->items())->map(fn ($it) => [
            'order_id'     => (int) $it->order_id,
            'order_status' => $it->order?->status,
            'quantity'     => (float) $it->quantity,
            'price'        => (float) $it->price,
            'created_at'   => optional($it->order?->created_at)->toIso8601String(),
            'delivered_at' => optional($it->order?->delivered_at)->toIso8601String(),
        ]);

        return response()->json([
            'error' => false,
            'batch' => [
                'id'                 => (int) $batch->id,
                'product_id'         => (int) $batch->product_id,
                'batch_date'         => optional($batch->batch_date)->toDateString(),
                'quantity_in'        => (float) $batch->quantity_in,
                'quantity_remaining' => (float) $batch->quantity_remaining,
                'status'             => $batch->status,
            ],
            'data'  => $items,
            'meta'  => [
                'current_page' => $movements->currentPage(),
                'last_page'    => $movements->lastPage(),
                'total'        => $movements->total(),
            ],
        ]);
    }

    /**
     * Số ngày dùng để tính trung bình bán & gợi ý nhập (cửa sổ trượt).
     */
    private const SUGGEST_WINDOW_DAYS = 7;

    /**
     * Hạn dùng (tươi) mặc định cho rau khi nhập batch, tính từ ngày nhập.
     * Dùng khi FE/farm không truyền expire_date thủ công.
     */
    public const DEFAULT_SHELF_LIFE_DAYS = 5;

    /**
     * GET /api/farm/stock-in/suggestions
     *
     * Dữ liệu cho màn "Khai báo nhập kho buổi sáng": mỗi SKU farm cung cấp kèm
     *   - avg_daily_sold  : TB số lượng bán/ngày trong {SUGGEST_WINDOW_DAYS} ngày qua
     *   - suggested_qty   : gợi ý nhập = round(avg_daily_sold) (tối thiểu 0)
     *   - price           : giá bán lẻ hiện tại (đ/kg) — để ước doanh thu
     *   - cost_price      : giá vốn mặc định trên pivot farm_product
     *   - stock           : tồn kho active hiện tại
     *   - sold_out_yesterday : true nếu hôm qua có bán nhưng tồn hiện = 0 (cháy hàng)
     *   - shelf_life_days / suggested_expire_date : hạn dùng tươi mặc định
     *
     * Sales window dựa trên zalo_order_items.farm_id (đã phân bổ) JOIN
     * zalo_orders status='delivered' + delivered_at — cùng nguồn với
     * FarmDashboardService để số liệu nhất quán. Timezone Asia/Ho_Chi_Minh.
     */
    public function suggestions(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $vnNow      = Carbon::now('Asia/Ho_Chi_Minh');
        $windowFrom = $vnNow->copy()->subDays(self::SUGGEST_WINDOW_DAYS - 1)->startOfDay();
        // So trực tiếp giờ VN với delivered_at (cũng lưu giờ VN — cột dateTime naive,
        // app.timezone=Asia/Ho_Chi_Minh). KHÔNG setTimezone('UTC') (HUB-01 TZ-fix B18).
        $yStart = $vnNow->copy()->subDay()->startOfDay();
        $yEnd   = $vnNow->copy()->subDay()->endOfDay();

        // Tổng bán trong cửa sổ + bán riêng hôm qua, group theo product_id.
        $salesRows = DB::table('zalo_order_items')
            ->join('zalo_orders', 'zalo_orders.id', '=', 'zalo_order_items.order_id')
            ->where('zalo_order_items.farm_id', $farm->id)
            ->where('zalo_orders.status', 'delivered')
            ->whereNotNull('zalo_orders.delivered_at')
            ->whereBetween('zalo_orders.delivered_at', [$windowFrom, $vnNow])
            ->groupBy('zalo_order_items.product_id')
            ->selectRaw('
                zalo_order_items.product_id AS product_id,
                COALESCE(SUM(zalo_order_items.quantity), 0) AS window_qty,
                COALESCE(SUM(CASE WHEN zalo_orders.delivered_at BETWEEN ? AND ? THEN zalo_order_items.quantity ELSE 0 END), 0) AS yesterday_qty
            ', [$yStart, $yEnd])
            ->get()
            ->keyBy('product_id');

        // Tồn kho active hiện tại theo product_id.
        $stockRows = DB::table('farm_stock_batches')
            ->where('farm_id', $farm->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, COALESCE(SUM(quantity_remaining), 0) AS stock')
            ->pluck('stock', 'product_id');

        // Danh sách SKU farm cung cấp (kèm giá bán + giá vốn pivot).
        $products = DB::table('zalo_products')
            ->join('farm_product', function ($j) use ($farm) {
                $j->on('farm_product.product_id', '=', 'zalo_products.id')
                  ->where('farm_product.farm_id', '=', $farm->id);
            })
            ->leftJoin('zalo_categories', 'zalo_categories.id', '=', 'zalo_products.category_id')
            ->orderBy('zalo_products.name')
            ->get([
                'zalo_products.id',
                'zalo_products.name',
                'zalo_products.image',
                'zalo_products.price',
                'zalo_products.category_id',
                'zalo_categories.name AS category_name',
                'farm_product.cost_price AS pivot_cost_price',
            ]);

        $window = self::SUGGEST_WINDOW_DAYS;
        $items = $products->map(function ($p) use ($salesRows, $stockRows, $window, $vnNow) {
            $sale         = $salesRows->get($p->id);
            $windowQty    = (float) ($sale->window_qty ?? 0);
            $yesterdayQty = (float) ($sale->yesterday_qty ?? 0);
            $avgDaily     = $windowQty / $window;
            $stock        = (float) ($stockRows[$p->id] ?? 0);

            return [
                'product_id'           => (int) $p->id,
                'name'                 => (string) $p->name,
                'category'             => $p->category_name !== null ? (string) $p->category_name : null,
                'image_url'            => $this->resolveImageUrl($p->image),
                'price'                => (float) $p->price,
                'cost_price'           => (float) ($p->pivot_cost_price ?? 0),
                'stock'                => round($stock, 2),
                'avg_daily_sold'       => round($avgDaily, 1),
                'window_days'          => $window,
                // Gợi ý nhập: làm tròn TB ngày, tối thiểu 0. Nếu cháy hàng hôm qua
                // mà chưa từng bán đủ thì vẫn gợi ý ít nhất phần đã bán hôm qua.
                'suggested_qty'        => (int) max(round($avgDaily), $yesterdayQty > 0 && $stock <= 0 ? ceil($yesterdayQty) : 0),
                // Cháy hàng hôm qua: có bán hôm qua nhưng giờ tồn = 0.
                'sold_out_yesterday'   => $yesterdayQty > 0 && $stock <= 0,
                'shelf_life_days'      => self::DEFAULT_SHELF_LIFE_DAYS,
                'suggested_expire_date'=> $vnNow->copy()->addDays(self::DEFAULT_SHELF_LIFE_DAYS)->toDateString(),
            ];
        })->values()->all();

        // Tổng gợi ý nhập hôm nay (banner "nên nhập khoảng Xkg").
        $suggestedTotal = array_sum(array_column($items, 'suggested_qty'));

        return response()->json([
            'error' => false,
            'data'  => $items,
            'meta'  => [
                'date'             => $vnNow->toDateString(),
                'window_days'      => $window,
                'suggested_total'  => $suggestedTotal,
                'shelf_life_days'  => self::DEFAULT_SHELF_LIFE_DAYS,
            ],
        ]);
    }

    /**
     * POST /api/farm/stock-in/batch
     * Khai báo nhập kho nhiều SKU một lần (màn "Khai báo nhập kho buổi sáng").
     * Mỗi dòng tạo một FarmStockBatch riêng trong một transaction.
     *
     * Body: {
     *   batch_date?: date,                       // mặc định hôm nay
     *   items: [{ product_id, quantity, expire_date?, cost_price?, note? }, ...]
     * }
     *
     * expire_date mỗi dòng: nếu không truyền, tự tính = batch_date + shelf_life_days.
     * cost_price mỗi dòng: nếu không truyền, lấy giá vốn pivot farm_product.
     */
    public function importBatch(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'batch_date'           => 'nullable|date',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer|exists:zalo_products,id',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.expire_date'  => 'nullable|date',
            'items.*.cost_price'   => 'nullable|numeric|min:0',
            'items.*.note'         => 'nullable|string|max:500',
        ]);

        $batchDate = $request->input('batch_date')
            ? Carbon::parse($request->input('batch_date'))->toDateString()
            : Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $rows = $request->input('items');

        // Kiểm soát: tất cả SKU phải thuộc farm (pivot farm_product). Tra một lần.
        $productIds  = collect($rows)->pluck('product_id')->map(fn ($id) => (int) $id)->unique();
        $allowed     = $farm->products()
            ->whereIn('zalo_products.id', $productIds)
            ->get(['zalo_products.id'])
            ->pluck('id')
            ->all();
        $allowedSet  = array_flip($allowed);

        $costDefaults = $farm->products()
            ->whereIn('zalo_products.id', $productIds)
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => (float) ($p->pivot->cost_price ?? 0)]);

        $notAllowed = $productIds->reject(fn ($id) => isset($allowedSet[$id]))->values();
        if ($notAllowed->isNotEmpty()) {
            return response()->json([
                'error'   => true,
                'message' => 'Một số sản phẩm chưa được gán cho farm của bạn. Vui lòng liên hệ admin.',
                'product_ids' => $notAllowed->all(),
            ], 403);
        }

        $created = DB::transaction(function () use ($rows, $farm, $batchDate, $costDefaults) {
            $out = [];
            foreach ($rows as $row) {
                $pid       = (int) $row['product_id'];
                $costPrice = isset($row['cost_price']) && $row['cost_price'] !== null
                    ? (float) $row['cost_price']
                    : (float) ($costDefaults[$pid] ?? 0);
                $expire = $row['expire_date']
                    ?? Carbon::parse($batchDate)->addDays(self::DEFAULT_SHELF_LIFE_DAYS)->toDateString();

                $batch = FarmStockBatch::create([
                    'farm_id'       => $farm->id,
                    'product_id'    => $pid,
                    'batch_date'    => $batchDate,
                    'quantity_in'   => (float) $row['quantity'],
                    'quantity_sold' => 0,
                    'cost_price'    => $costPrice,
                    'expire_date'   => $expire,
                    'status'        => 'active',
                    'note'          => $row['note'] ?? null,
                ]);

                $out[] = [
                    'id'                 => (int) $batch->id,
                    'product_id'         => (int) $batch->product_id,
                    'batch_date'         => $batch->batch_date?->toDateString(),
                    'expire_date'        => $batch->expire_date?->toDateString(),
                    'quantity_in'        => (float) $batch->quantity_in,
                    'quantity_remaining' => (float) $batch->quantity_remaining,
                    'cost_price'         => (float) $batch->cost_price,
                    'status'             => $batch->status,
                ];
            }
            return $out;
        });

        return response()->json([
            'error' => false,
            'data'  => $created,
            'meta'  => [
                'count'      => count($created),
                'batch_date' => $batchDate,
            ],
        ], 201);
    }

    /**
     * POST /api/farm/inventory/import
     * Tạo batch mới. Khác API cũ: KHÔNG nhận {id} trên URL — body chứa product_id.
     *
     * Body: { product_id, quantity, cost_price?, expire_date?, batch_date?, note? }
     */
    public function import(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'product_id'  => 'required|integer|exists:zalo_products,id',
            'quantity'    => 'required|numeric|min:0.01',
            'cost_price'  => 'nullable|numeric|min:0',
            'expire_date' => 'nullable|date',
            'batch_date'  => 'nullable|date',
            'note'        => 'nullable|string|max:500',
        ]);

        // Kiểm soát: farm chỉ được nhập SKU mà farm cung cấp (có trong pivot farm_product).
        $isAllowed = $farm->products()->where('zalo_products.id', $request->product_id)->exists();
        if (!$isAllowed) {
            return response()->json([
                'error'   => true,
                'message' => 'Sản phẩm này chưa được gán cho farm của bạn. Vui lòng liên hệ admin.',
            ], 403);
        }

        $costPrice = $request->input('cost_price');
        if ($costPrice === null) {
            // Lấy giá vốn mặc định trên pivot.
            $pivot = $farm->products()->where('zalo_products.id', $request->product_id)->first();
            $costPrice = (float) ($pivot?->pivot->cost_price ?? 0);
        }

        $batch = FarmStockBatch::create([
            'farm_id'       => $farm->id,
            'product_id'    => (int) $request->product_id,
            'batch_date'    => $request->batch_date ?? now()->toDateString(),
            'quantity_in'   => (float) $request->quantity,
            'quantity_sold' => 0,
            'cost_price'    => (float) $costPrice,
            'expire_date'   => $request->expire_date,
            'status'        => 'active',
            'note'          => $request->note,
        ]);
        $batch->refresh(); // reload generated column quantity_remaining from MySQL

        return response()->json([
            'error' => false,
            'data'  => [
                'id'                 => (int) $batch->id,
                'product_id'         => (int) $batch->product_id,
                'batch_date'         => $batch->batch_date?->toDateString(),
                'expire_date'        => $batch->expire_date?->toDateString(),
                'quantity_in'        => (float) $batch->quantity_in,
                'quantity_remaining' => (float) $batch->quantity_remaining,
                'cost_price'         => (float) $batch->cost_price,
                'status'             => $batch->status,
            ],
        ], 201);
    }

    /**
     * POST /api/farm/inventory/{batchId}/close
     * Đóng/huỷ batch — set status='recalled' (nếu có lý do thu hồi) hoặc 'depleted'.
     * Quantity_remaining KHÔNG đổi (đây là generated col). Nếu sau này admin cần
     * "tiêu huỷ N kg" thì làm thêm endpoint adjust qty_in/qty_sold riêng.
     *
     * Body: { reason: 'recalled'|'depleted', note? }
     */
    public function close(Request $request, int $id): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $request->validate([
            'reason' => 'required|in:recalled,depleted,expired',
            'note'   => 'nullable|string|max:500',
        ]);

        $batch = FarmStockBatch::where('id', $id)
            ->where('farm_id', $farm->id)
            ->first();
        if (!$batch) {
            return response()->json(['error' => true, 'message' => 'Batch không tồn tại'], 404);
        }

        if ($batch->status !== 'active') {
            return response()->json([
                'error'   => true,
                'message' => "Batch đang ở trạng thái '{$batch->status}', không thể đóng nữa.",
            ], 422);
        }

        $batch->status = (string) $request->reason;
        if ($request->note) {
            $batch->note = trim(($batch->note ? $batch->note . "\n" : '') . "[close] {$request->note}");
        }
        $batch->save();

        return response()->json([
            'error' => false,
            'data'  => [
                'id'     => (int) $batch->id,
                'status' => $batch->status,
            ],
        ]);
    }
}
