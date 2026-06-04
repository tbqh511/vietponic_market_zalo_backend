<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng order_farm_assignments — "phiếu đóng gói" cho từng cặp (order, farm).
 *
 * Một đơn có thể chứa item của NHIỀU farm (FEFO split trong StockService).
 * Một nhân viên đóng gói (packer) thuộc đúng 1 farm. Vì vậy đơn vị phân công
 * là PHẦN-CỦA-MỘT-FARM trong một đơn = cặp (order_id, farm_id), không phải
 * toàn đơn. Mỗi row là một phiếu đóng gói riêng cho phần của farm đó.
 *
 * Lifecycle status: unassigned → assigned → packing → packed.
 * Khi TẤT CẢ assignment của một order đã 'packed', PackingService đẩy order
 * sang 'delivering' (bàn giao vận chuyển) — xem ZaloApiController::updateStatus.
 *
 * Phân quyền: chủ farm (owner) gán assigned_customer_id cho staff; staff chỉ
 * thấy/thao tác assignment có assigned_customer_id = chính mình.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_farm_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('farm_id');

            // Packer được gán (staff hoặc owner của farm). Null = chưa gán.
            $table->unsignedBigInteger('assigned_customer_id')->nullable();

            // Ai thực hiện việc gán: admin web (user) HOẶC chủ farm qua app (customer).
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->unsignedBigInteger('assigned_by_customer_id')->nullable();

            $table->string('status', 16)->default('unassigned');

            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('packing_started_at')->nullable();
            $table->dateTime('packed_at')->nullable();

            $table->string('note', 255)->nullable();

            $table->timestamps();

            // 1 phiếu duy nhất cho mỗi cặp (order, farm).
            $table->unique(['order_id', 'farm_id']);
            $table->index(['farm_id', 'status']);
            $table->index(['assigned_customer_id', 'status']);

            $table->foreign('order_id')
                ->references('id')->on('zalo_orders')
                ->onDelete('cascade');

            $table->foreign('farm_id')
                ->references('id')->on('farms')
                ->onDelete('cascade');

            $table->foreign('assigned_customer_id')
                ->references('id')->on('customers')
                ->onDelete('set null');
        });

        // Backfill: tạo phiếu 'unassigned' cho mọi cặp (order, farm) distinct của
        // các đơn đang còn xử lý (chưa delivered/cancelled). Đơn đã giao/huỷ không
        // cần đóng gói nên bỏ qua. Bọc try để môi trường test (sqlite) chưa có data
        // vẫn migrate sạch.
        try {
            $pairs = DB::table('zalo_order_items as oi')
                ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
                ->whereNotNull('oi.farm_id')
                ->whereIn('o.status', ['pending', 'confirmed', 'preparing', 'delivering'])
                ->select('oi.order_id', 'oi.farm_id')
                ->distinct()
                ->get();

            $now = now();
            $rows = $pairs->map(fn ($p) => [
                'order_id'   => $p->order_id,
                'farm_id'    => $p->farm_id,
                'status'     => 'unassigned',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('order_farm_assignments')->insert($chunk);
            }
        } catch (\Throwable $e) {
            // Bảng nguồn chưa tồn tại / chưa có data — bỏ qua, không chặn migrate.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_farm_assignments');
    }
};
