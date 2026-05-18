<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng farm_stock_batches — mỗi lần farm nhập rau là một batch (lô).
 *
 * Tồn kho thực tế = SUM(quantity_remaining) của các batch active của product đó.
 * Khi tạo order, hệ thống phân bổ FEFO (First-Expired-First-Out): batch nào
 * có expire_date sớm nhất bị trừ trước, batch không có expire_date đi sau cùng.
 *
 * quantity_remaining là generated column (stored) — DB tự tính, app chỉ đọc.
 * Để FEFO query nhanh, có index riêng (product_id, expire_date, status).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_stock_batches', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('product_id');

            $table->date('batch_date');

            $table->decimal('quantity_in', 10, 2);
            $table->decimal('quantity_sold', 10, 2)->default(0);

            // Generated stored column trên MySQL/MariaDB — DB tự cập nhật.
            // SQLite (test env) không support storedAs → dùng cột thường với default.
            // KHÔNG thêm cột này vào $fillable của model, chỉ đọc (trừ test helper).
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
                $table->decimal('quantity_remaining', 10, 2)->default(0);
            } else {
                $table->decimal('quantity_remaining', 10, 2)
                    ->storedAs('quantity_in - quantity_sold');
            }

            $table->decimal('cost_price', 12, 2);

            $table->date('expire_date')->nullable();

            $table->enum('status', ['active', 'depleted', 'expired', 'recalled'])
                ->default('active');

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['farm_id', 'batch_date']);
            $table->index(['product_id', 'status']);
            // Index cho FEFO query — bao gồm expire_date để optimizer tận dụng được.
            $table->index(['product_id', 'expire_date', 'status']);

            $table->foreign('farm_id')
                ->references('id')->on('farms')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')->on('zalo_products')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_stock_batches');
    }
};
