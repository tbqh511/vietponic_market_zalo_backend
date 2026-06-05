<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cờ is_packing_hub vào farms — đánh dấu farm là "bộ phận đóng gói"
 * (Package Hub) của Vietponics.
 *
 * Chỉ farm là Package Hub mới có quyền vào khâu xử lý/đóng gói đơn (xác nhận,
 * phân công, đóng gói, bàn giao). Owner của hub đóng gói TOÀN BỘ đơn, bất kể
 * hàng đến từ farm nào (FEFO). Các farm thường chỉ xem chỉ-đọc đơn có hàng của
 * mình. Cho phép NHIỀU hub (chọn hub id nhỏ nhất khi cần — Farm::primaryPackingHub()).
 *
 * Không đụng tới zalo_order_items.farm_id (nguồn doanh thu per-farm) — khâu đóng
 * gói tách hẳn khỏi quyền sở hữu hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->boolean('is_packing_hub')->default(false)->after('is_active');
            $table->index(['is_packing_hub', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex(['is_packing_hub', 'is_active']);
            $table->dropColumn('is_packing_hub');
        });
    }
};
