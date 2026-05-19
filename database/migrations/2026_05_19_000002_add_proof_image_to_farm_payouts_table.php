<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột lưu đường dẫn ảnh UNC (ủy nhiệm chi / ảnh chụp chứng từ chuyển khoản)
 * cho mỗi lệnh chi trả farm.
 *
 * File được lưu vào disk 'public' (storage/app/public/farm_payouts/) — cần
 * `php artisan storage:link` đã chạy để render qua <img src="{{ asset(...) }}">.
 * Nullable vì payout ở trạng thái draft/pending chưa có ảnh, chỉ set khi
 * admin bấm "Xác nhận đã thanh toán".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_payouts', function (Blueprint $table) {
            $table->string('proof_image_path')->nullable()->after('transaction_ref');
        });
    }

    public function down(): void
    {
        Schema::table('farm_payouts', function (Blueprint $table) {
            $table->dropColumn('proof_image_path');
        });
    }
};
