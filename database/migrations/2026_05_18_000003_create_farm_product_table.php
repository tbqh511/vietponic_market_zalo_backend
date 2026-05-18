<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot many-to-many giữa farms và zalo_products.
 *
 * Một SKU (vd "Xà lách romaine") có thể được nhiều farm cùng cung cấp.
 * cost_price ở đây là giá vốn mặc định cho cặp farm-product — khi nhập
 * batch mới mà không khai cost_price riêng thì lấy giá này.
 *
 * is_primary đánh dấu farm "chính" cho sản phẩm — admin UI có thể dùng
 * để gợi ý hoặc gắn nhãn nguồn gốc trên frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_product', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('product_id');

            $table->decimal('cost_price', 12, 2);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['farm_id', 'product_id']);
            $table->index('product_id');

            $table->foreign('farm_id')
                ->references('id')->on('farms')
                ->onDelete('cascade');

            // zalo_products.id là unsignedBigInteger (không auto-increment),
            // không phải bigIncrements — phải reference đúng cột.
            $table->foreign('product_id')
                ->references('id')->on('zalo_products')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_product');
    }
};
