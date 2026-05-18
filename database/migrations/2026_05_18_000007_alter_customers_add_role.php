<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm role + farm_partner_status vào customers.
 *
 * Thay thế cơ chế cũ (bảng farm_partners.status đã bị drop ở migration đầu).
 * Quy ước:
 *   - role = 'customer' (mặc định): user thường
 *   - role = 'farm_partner': khi farm_partner_status = 'approved' mới có quyền truy
 *     cập Farm Hub. 'requested' = đang chờ admin duyệt, 'suspended' = bị khoá.
 *   - role = 'admin' để dành cho tương lai (hiện tại quản trị viên dùng bảng users riêng).
 *
 * Khách truy cập legacy farm_partners (đã drop) cần được tái cấu hình thủ công
 * qua admin nếu trước đó họ là farm partner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('role', ['customer', 'farm_partner', 'admin'])
                ->default('customer')
                ->after('isActive');

            $table->enum('farm_partner_status', ['none', 'requested', 'approved', 'suspended'])
                ->default('none')
                ->after('role');

            $table->index(['role', 'farm_partner_status']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['role', 'farm_partner_status']);
            $table->dropColumn(['role', 'farm_partner_status']);
        });
    }
};
