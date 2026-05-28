<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lưu OA access_token + refresh_token (single-row).
 *
 * access_token sống ~25h và refresh_token đổi sau MỖI lần refresh → không thể để
 * trong .env. Token lần đầu lấy thủ công từ Zalo OA console rồi seed qua
 * `php artisan zalo:oa-token --access=... --refresh=...`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zalo_oa_tokens', function (Blueprint $table) {
            $table->id();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zalo_oa_tokens');
    }
};
