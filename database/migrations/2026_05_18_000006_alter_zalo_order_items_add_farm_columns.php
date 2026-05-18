<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng zalo_order_items để ghi nguồn gốc farm + giá vốn snapshot cho phân bổ FEFO.
 *
 * Một order_item giờ có thể được split (nếu cần lấy từ nhiều batch) — trong
 * ZaloApiController flow tạo order sẽ insert nhiều row cho cùng product_id,
 * mỗi row gắn với một farm_stock_batch_id riêng.
 *
 * cost_price_snapshot được chốt tại thời điểm tạo order (= batch.cost_price)
 * để báo cáo doanh thu cho farm không bị ảnh hưởng nếu sau này farm đổi giá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('farm_stock_batch_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('farm_id')->nullable()->after('farm_stock_batch_id');
            $table->decimal('cost_price_snapshot', 12, 2)->nullable()->after('farm_id');

            $table->index('farm_id');
            $table->index('farm_stock_batch_id');

            $table->foreign('farm_stock_batch_id')
                ->references('id')->on('farm_stock_batches')
                ->onDelete('set null');

            $table->foreign('farm_id')
                ->references('id')->on('farms')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('zalo_order_items', function (Blueprint $table) {
            $table->dropForeign(['farm_stock_batch_id']);
            $table->dropForeign(['farm_id']);
            $table->dropIndex(['farm_id']);
            $table->dropIndex(['farm_stock_batch_id']);
            $table->dropColumn(['farm_stock_batch_id', 'farm_id', 'cost_price_snapshot']);
        });
    }
};
