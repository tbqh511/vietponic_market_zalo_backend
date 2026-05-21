<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            // FK voucher gốc (null khi không dùng / voucher đã bị xoá).
            $table->unsignedBigInteger('voucher_id')->nullable()->after('shipping_service_name');
            // Snapshot code tại thời điểm đặt — bảo toàn lịch sử khi voucher bị sửa/xoá.
            $table->string('voucher_code', 50)->nullable()->after('voucher_id');
            // Snapshot tổng số tiền đã giảm (subtotal + shipping_discount).
            $table->unsignedInteger('discount_amount')->default(0)->after('voucher_code');

            $table->index('voucher_id');
            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropIndex(['voucher_id']);
            $table->dropColumn(['voucher_id', 'voucher_code', 'discount_amount']);
        });
    }
};
