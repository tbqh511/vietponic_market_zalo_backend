<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtp_wards', function (Blueprint $table) {
            // VTP v3 listWardsNew trả ward theo province, không qua district nữa
            $table->unsignedInteger('province_id')->nullable()->after('id');
            $table->index('province_id');
        });
    }

    public function down(): void
    {
        Schema::table('vtp_wards', function (Blueprint $table) {
            $table->dropIndex(['province_id']);
            $table->dropColumn('province_id');
        });
    }
};
