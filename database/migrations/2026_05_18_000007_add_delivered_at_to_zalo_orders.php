<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột delivered_at vào zalo_orders cho Farm Partner Hub.
 *
 * Lý do: dashboard farm tính doanh thu trên đơn status='delivered'. Hiện tại
 * zalo_orders chỉ có created_at + received_at; received_at được dùng inconsistent
 * (có lúc set khi tạo đơn, có lúc null) nên không phải nguồn tin cậy để tính
 * cutoff thời gian giao.
 *
 * delivered_at được set bởi flow chuyển status sang 'delivered' (controller
 * admin PATCH orders/{id}/status hoặc webhook VTP). Để đảm bảo backfill cho
 * đơn cũ đã 'delivered' trước migration, set delivered_at = received_at fallback
 * created_at cho mọi đơn có status='delivered'.
 *
 * Index để FarmDashboardService query range nhanh:
 *   WHERE status='delivered' AND delivered_at BETWEEN ? AND ?
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->dateTime('delivered_at')->nullable()->after('received_at');
            $table->index(['status', 'delivered_at']);
        });

        // Backfill cho đơn đã delivered trước khi có cột này — tránh dashboard
        // thiếu lịch sử. COALESCE: ưu tiên received_at, fallback created_at.
        \DB::statement("
            UPDATE zalo_orders
            SET delivered_at = COALESCE(received_at, created_at)
            WHERE status = 'delivered' AND delivered_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'delivered_at']);
            $table->dropColumn('delivered_at');
        });
    }
};
