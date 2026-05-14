<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Ghi nhận farm-partner customer khi họ nhập/xuất kho từ Mini App
            // Tách biệt với created_by (FK → users/admin)
            $table->unsignedBigInteger('farm_customer_id')->nullable()->after('created_by');
            $table->foreign('farm_customer_id')
                ->references('id')->on('customers')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['farm_customer_id']);
            $table->dropColumn('farm_customer_id');
        });
    }
};
