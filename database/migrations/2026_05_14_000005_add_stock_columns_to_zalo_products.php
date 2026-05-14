<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_products', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('detail');
            $table->integer('stock_reserved')->default(0)->after('stock');
            $table->integer('reorder_point')->default(10)->after('stock_reserved');
        });
    }

    public function down(): void
    {
        Schema::table('zalo_products', function (Blueprint $table) {
            $table->dropColumn(['stock', 'stock_reserved', 'reorder_point']);
        });
    }
};
