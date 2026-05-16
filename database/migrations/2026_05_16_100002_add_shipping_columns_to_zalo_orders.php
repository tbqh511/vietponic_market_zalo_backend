<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            // total = subtotal + shipping_fee, server tự tính lại để chống tamper
            $table->unsignedInteger('subtotal')->default(0)->after('total');
            $table->unsignedInteger('shipping_fee')->default(0)->after('subtotal');
            $table->string('shipping_service_code', 32)->nullable()->after('shipping_fee');
            $table->string('shipping_service_name')->nullable()->after('shipping_service_code');
            // Trọng lượng quy đổi VTP trả về — lưu để đối soát khi có chênh phí
            $table->unsignedInteger('exchange_weight')->nullable()->after('shipping_service_name');
        });
    }

    public function down()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal', 'shipping_fee',
                'shipping_service_code', 'shipping_service_name',
                'exchange_weight',
            ]);
        });
    }
};
