<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // 3 loại MVP: percent | fixed | free_shipping
            $table->string('discount_type', 20);
            // percent → 0..100; fixed → VND; free_shipping → 0 (unlimited cap) hoặc cap VND
            $table->decimal('discount_value', 12, 2)->default(0);
            // Cap tối đa khi percent. null = không cap.
            $table->unsignedInteger('max_discount_amount')->nullable();
            $table->unsignedInteger('min_order_amount')->default(0);
            // null = không giới hạn tổng lượt
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            // public=true → liệt kê trong danh sách "Voucher khả dụng"
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_public']);
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('vouchers');
    }
};
