<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm thông tin tài khoản ngân hàng nhận tiền cho Farm Partner.
 *
 * Tách riêng với affiliate_bank_* vì 1 customer có thể vừa là CTV vừa là Farm
 * Partner và muốn nhận 2 dòng tiền vào 2 tài khoản khác nhau. Admin nhập trong
 * form sửa Farm (hoặc khi tạo lệnh chi). Hiển thị trên drawer "Chi tiết Lệnh chi"
 * để kế toán đọc số tài khoản và chuyển khoản.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('farm_bank_name', 120)->nullable()->after('farm_partner_status');
            $table->string('farm_bank_account', 60)->nullable()->after('farm_bank_name');
            $table->string('farm_bank_holder', 120)->nullable()->after('farm_bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'farm_bank_name',
                'farm_bank_account',
                'farm_bank_holder',
            ]);
        });
    }
};
