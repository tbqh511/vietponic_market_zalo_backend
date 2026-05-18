<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
     *   ?view=batches|skus    — default batches
     *   ?status=active|depleted|expired|recalled|all   — default active
     *   ?product_id=...
     *   ?q= search SKU name (khi view=skus)
     *   ?per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Farm $farm */
        $farm = $request->attributes->get('farm');

        $view = $request->query('view', 'batches') === 'skus' ? 'skus' : 'batches';

        if ($view === 'skus') {
            return $this->indexSkus($request, $farm);
        }
        return $this->indexBatches($request, $farm);
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
     * View aggregate theo SKU: 1 row = 1 product, tổng remaining + earliest expire.
     * Chỉ tính batch active của farm hiện tại.
     */
    private function indexSkus(Request $request, Farm $farm): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $rows = FarmStockBatch::query()
            ->leftJoin('zalo_products', 'zalo_products.id', '=', 'farm_stock_batches.product_id')
            ->where('farm_stock_batches.farm_id', $farm->id)
            ->where('farm_stock_batches.status', 'active')
            ->where('farm_stock_batches.quantity_remaining', '>', 0)
            ->when($q !== '', fn ($qb) => $qb->where('zalo_products.name', 'like', "%{$q}%"))
            ->selectRaw('
                farm_stock_batches.product_id AS product_id,
                COALESCE(zalo_products.name, \'\') AS name,
                COALESCE(zalo_products.image, \'\') AS image,
                SUM(farm_stock_batches.quantity_remaining) AS total_remaining,
                COUNT(*) AS batches_count,
                MIN(farm_stock_batches.expire_date) AS earliest_expire
            ')
            ->groupBy('farm_stock_batches.product_id', 'name', 'image')
            ->orderByDesc('total_remaining')
            ->get();

        $items = $rows->map(fn ($r) => [
            'product_id'      => (int) $r->product_id,
            'name'            => (string) $r->name,
            'image'           => (string) $r->image,
            'total_remaining' => round((float) $r->total_remaining, 2),
            'batches_count'   => (int) $r->batches_count,
            'earliest_expire' => $r->earliest_expire,
        ])->all();

        return response()->json([
            'error' => false,
            'data'  => $items,
            'meta'  => ['view' => 'skus'],
        ]);
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
