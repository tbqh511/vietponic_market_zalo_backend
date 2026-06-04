<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng order_packing_logs — nhật ký hoạt động đóng gói (append-only).
 *
 * Mục tiêu: truy vết sự cố — biết chính xác nhân viên/chủ farm/admin nào đã
 * thao tác gì trên đơn nào, lúc nào. Không bao giờ update/delete row (audit
 * trail bất biến).
 *
 * actor có thể là customer (staff/owner thao tác qua Mini App) hoặc user
 * (admin thao tác qua web) → 2 cột FK nullable + actor_label denormalized để
 * report đọc nhanh không cần join (và giữ tên tại thời điểm log dù sau này
 * người đó bị xoá/đổi tên).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_packing_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('farm_id')->nullable();

            // Ai thao tác — customer (app) hoặc admin user (web). Có thể cả hai null
            // nếu là hành động hệ thống tự sinh.
            $table->unsignedBigInteger('actor_customer_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            // Denormalized "Nguyễn Văn A (staff)" / "Admin (web)" tại thời điểm log.
            $table->string('actor_label', 191)->nullable();

            // assigned | reassigned | unassigned | packing_started | packed |
            // status_changed
            $table->string('action', 32);

            // Cho action đổi trạng thái order (vd preparing → delivering).
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();

            $table->json('meta')->nullable();

            // Chỉ created_at (append-only, không có updated_at).
            $table->dateTime('created_at')->useCurrent();

            $table->index('order_id');
            $table->index(['farm_id', 'created_at']);
            $table->index(['actor_customer_id', 'created_at']);

            $table->foreign('order_id')
                ->references('id')->on('zalo_orders')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_packing_logs');
    }
};
