<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_products', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('reorder_point');
            $table->enum('system_unit', ['g', 'ml', 'piece'])->default('piece')->after('unit_id');
            $table->decimal('conversion_factor', 12, 3)->default(1)->after('system_unit');
            $table->foreign('unit_id')->references('id')->on('zalo_units')->onDelete('set null');
        });

        DB::table('zalo_products')->update([
            'system_unit'       => 'piece',
            'conversion_factor' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::table('zalo_products', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'system_unit', 'conversion_factor']);
        });
    }
};
