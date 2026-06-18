<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->float('distance_km')->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->dropColumn('distance_km');
        });
    }
};
