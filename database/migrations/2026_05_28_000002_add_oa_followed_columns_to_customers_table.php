<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theo dõi follow OA cho kênh OA Message (miễn phí).
 *
 * Cập nhật qua OA follow/unfollow webhook (POST /oa/webhook). Job notification ưu
 * tiên gửi OA Message khi oa_followed = true, fallback ZNS theo SĐT nếu chưa follow.
 *
 * Lưu ý CLAUDE.md: customers chia sẻ với BDS — chỉ thêm cột nullable / có default,
 * không NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('oa_followed')->default(false)->after('fcm_id');
            $table->timestamp('oa_followed_at')->nullable()->after('oa_followed');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['oa_followed', 'oa_followed_at']);
        });
    }
};
