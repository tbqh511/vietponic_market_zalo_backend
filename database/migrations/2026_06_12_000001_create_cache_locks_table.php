<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng cache_locks — backing store cho atomic lock của database cache driver.
 *
 * Lý do (B17 / ORDER-08): chống TOCTOU khi 2 request /checkout trùng đến gần như
 * đồng thời. Cache store mặc định của production là 'file' — KHÔNG hỗ trợ atomic
 * lock (Cache::lock() ném BadMethodCallException). Database lock thì có, nhưng cần
 * bảng này. Controller dùng Cache::store('database')->lock(...) để serialize phần
 * tạo đơn (xem ZaloApiController::store()).
 *
 * Schema chuẩn Laravel cho DatabaseStore lock (key PK + owner + expiration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cache_locks')) {
            return;
        }

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
    }
};
