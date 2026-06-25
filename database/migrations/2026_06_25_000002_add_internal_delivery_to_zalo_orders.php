<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm hỗ trợ giao hàng nội bộ (internal delivery) cho zalo_orders.
 *
 *   delivery_method  — 'vtp' (default) | 'internal'. Nếu 'internal', listener
 *                      CreateVtpOrderOnPayment bỏ qua tạo VTP order.
 *   shipper_customer_id — FK tới customers.id: shipper nội bộ được gán.
 *   picked_up_at     — Thời điểm shipper xác nhận đã nhận hàng (pickup).
 *
 * Status flow thêm trạng thái 'out_for_delivery' cho internal:
 *   preparing → (handoff → shipper) → delivering → (pickup) → out_for_delivery
 *   → (deliver) → delivered.
 * VTP flow không đổi: preparing → delivering → (VTP webhook) → delivered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->string('delivery_method', 20)->default('vtp')->after('status');
            $table->unsignedBigInteger('shipper_customer_id')->nullable()->after('delivery_method');
            $table->dateTime('picked_up_at')->nullable()->after('shipper_customer_id');

            $table->foreign('shipper_customer_id')
                ->references('id')->on('customers')
                ->nullOnDelete();

            $table->index('shipper_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->dropForeign(['shipper_customer_id']);
            $table->dropIndex(['shipper_customer_id']);
            $table->dropColumn(['delivery_method', 'shipper_customer_id', 'picked_up_at']);
        });
    }
};
