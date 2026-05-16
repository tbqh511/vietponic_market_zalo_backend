<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtp_wards', function (Blueprint $table) {
            // VTP v3 listWardsNew không bảo đảm mọi ward đều có DISTRICT_ID
            // (một số tỉnh trả null sau sáp nhập hành chính tháng 7/2025)
            $table->unsignedInteger('district_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vtp_wards', function (Blueprint $table) {
            $table->unsignedInteger('district_id')->nullable(false)->change();
        });
    }
};
