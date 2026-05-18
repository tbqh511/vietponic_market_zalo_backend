<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng farms — đối tác cung cấp rau cho Vietponics.
 *
 * Một farm có 1 customer làm chủ (owner_customer_id). Một customer có thể
 * chỉ có 1 farm (enforced bằng unique index ở mức owner_customer_id IS NOT NULL).
 * Admin (users.id) là người duyệt yêu cầu mở farm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('code', 50)->unique();
            $table->string('name');

            // Chủ farm — customer Zalo đã được admin duyệt làm farm partner
            $table->unsignedBigInteger('owner_customer_id')->nullable();

            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('description')->nullable();

            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // commission_rate là tỷ lệ farm nhận về (vd 0.8500 = 85% giá vốn về farm)
            $table->decimal('commission_rate', 5, 4)->default(0.8500);

            $table->enum('payment_cycle', ['weekly', 'biweekly', 'monthly'])->default('weekly');

            $table->boolean('is_active')->default(false);

            // Quy trình duyệt: admin set approved_at + approved_by khi accept
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'approved_at']);
            $table->index('owner_customer_id');

            $table->foreign('owner_customer_id')
                ->references('id')->on('customers')
                ->onDelete('set null');

            $table->foreign('approved_by')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
