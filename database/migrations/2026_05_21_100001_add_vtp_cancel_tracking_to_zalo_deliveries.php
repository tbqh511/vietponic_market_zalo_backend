<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theo dõi quá trình hủy đơn VTP để cron vtp:retry-cancel có thể recover.
 *
 *  - vtp_cancel_requested_at: ZaloApiController::cancelVtpOrderIfExists() set khi
 *    ý định cancel đầu tiên (kể cả khi attempt thành công — VTP cần ~1h để
 *    đổi status code sang 105). Cron sẽ retry nếu sau X giờ mà vtp_status_code
 *    vẫn chưa phải 101/105/107/201/503/504/505.
 *  - vtp_cancel_attempts: counter để bound retries (max ~5 lần).
 *  - vtp_cancel_last_error: error message từ lần thử gần nhất (cho admin debug).
 *  - vtp_cancel_failed_at: timestamp của lần fail cuối — cron dùng để rate-limit.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->timestamp('vtp_cancel_requested_at')->nullable()->after('vtp_create_response');
            $table->unsignedSmallInteger('vtp_cancel_attempts')->default(0)->after('vtp_cancel_requested_at');
            $table->timestamp('vtp_cancel_failed_at')->nullable()->after('vtp_cancel_attempts');
            $table->string('vtp_cancel_last_error', 500)->nullable()->after('vtp_cancel_failed_at');

            // Index để cron retry-cancel query nhanh.
            $table->index(['vtp_cancel_requested_at', 'vtp_status_code'], 'idx_vtp_cancel_pending');
        });
    }

    public function down()
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->dropIndex('idx_vtp_cancel_pending');
            $table->dropColumn([
                'vtp_cancel_requested_at',
                'vtp_cancel_attempts',
                'vtp_cancel_failed_at',
                'vtp_cancel_last_error',
            ]);
        });
    }
};
