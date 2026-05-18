<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gỡ bỏ schema farm-partner / stock-movement cũ để chuyển sang
 * mô hình Farm Partner Hub mới (farms + farm_stock_batches + FEFO).
 *
 * Thứ tự drop quan trọng: bảng con (logs, items với FK) trước, cha sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bỏ các cột stock-integer trên zalo_products vì tồn kho từ giờ
        // được tính tổng từ farm_stock_batches.quantity_remaining
        if (Schema::hasColumn('zalo_products', 'stock_reserved')) {
            Schema::table('zalo_products', function (Blueprint $table) {
                $table->dropColumn(['stock', 'stock_reserved', 'reorder_point']);
            });
        }

        Schema::dropIfExists('farm_partner_logs');
        Schema::dropIfExists('farm_partners');
        Schema::dropIfExists('stock_movements');
    }

    /**
     * Down chỉ tái tạo các bảng/cột với cấu trúc tối thiểu để rollback an toàn,
     * dữ liệu cũ KHÔNG được phục hồi.
     */
    public function down(): void
    {
        Schema::table('zalo_products', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('detail');
            $table->integer('stock_reserved')->default(0)->after('stock');
            $table->integer('reorder_point')->default(10)->after('stock_reserved');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('movement_type', [
                'import', 'export', 'adjustment', 'return', 'damage', 'reserved', 'unreserved',
            ]);
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('farm_customer_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('product_id')->references('id')->on('zalo_products')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('zalo_orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('farm_customer_id')->references('id')->on('customers')->onDelete('set null');
        });

        Schema::create('farm_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('farm_name')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique('customer_id');
        });

        Schema::create('farm_partner_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_partner_id')->constrained('farm_partners')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('action');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
