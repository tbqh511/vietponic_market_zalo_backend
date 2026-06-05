<?php

use App\Models\OrderFarmAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chuyển phiếu đóng gói từ mô hình per-farm sang mô hình PACKAGE HUB.
 *
 * Trước: mỗi cặp (order, farm sở hữu hàng) là một phiếu — đơn nhiều farm có
 * nhiều phiếu. Sau: mỗi đơn có ĐÚNG 1 phiếu thuộc Package Hub (farm có
 * is_packing_hub, id nhỏ nhất). Hub đóng gói toàn bộ đơn.
 *
 * Backfill có điều kiện, idempotent, sqlite-safe (bọc try/catch để test env và
 * DB chưa có data vẫn migrate sạch):
 *   1. Xác định hub farm (min id trong is_packing_hub=1 + active + approved).
 *      Nếu CHƯA có hub → return (data cũ giữ nguyên; admin set hub sau, runtime
 *      ensureAssignmentsExist sẽ sinh phiếu hub cho đơn đang xử lý).
 *   2. Tạo 1 phiếu hub 'unassigned' cho mỗi đơn đang xử lý có hàng (insertOrIgnore
 *      theo unique order_id+farm_id).
 *   3. Dọn phiếu cũ per-farm của đơn đang xử lý (farm_id != hub) — mất trạng
 *      thái đóng dở giữa chừng là chấp nhận được (theo quyết định: data dev/demo).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            // 1. Hub farm "chính" — min id trong các hub đang hoạt động.
            $hubId = DB::table('farms')
                ->where('is_packing_hub', true)
                ->where('is_active', true)
                ->whereNotNull('approved_at')
                ->orderBy('id')
                ->value('id');

            if (! $hubId) {
                return; // Chưa cấu hình hub — không backfill.
            }

            $processing = ['pending', 'confirmed', 'preparing', 'delivering'];

            // 2. Mỗi đơn đang xử lý có hàng → đảm bảo có phiếu hub.
            $orderIds = DB::table('zalo_orders as o')
                ->whereIn('o.status', $processing)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('zalo_order_items as oi')
                      ->whereColumn('oi.order_id', 'o.id');
                })
                ->pluck('o.id');

            $now  = now();
            $rows = $orderIds->map(fn ($orderId) => [
                'order_id'   => $orderId,
                'farm_id'    => $hubId,
                'status'     => OrderFarmAssignment::STATUS_UNASSIGNED,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('order_farm_assignments')->insertOrIgnore($chunk);
            }

            // 3. Dọn phiếu cũ per-farm (không phải hub) của đơn đang xử lý.
            DB::table('order_farm_assignments')
                ->where('farm_id', '!=', $hubId)
                ->whereIn('order_id', function ($q) use ($processing) {
                    $q->select('id')->from('zalo_orders')->whereIn('status', $processing);
                })
                ->delete();
        } catch (\Throwable $e) {
            // Bảng nguồn chưa tồn tại / chưa có data — bỏ qua, không chặn migrate.
        }
    }

    public function down(): void
    {
        // Không khôi phục phiếu per-farm cũ (data đã bị gộp về hub). No-op an toàn.
    }
};
