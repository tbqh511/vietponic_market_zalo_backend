<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_order_items', function (Blueprint $table) {
            $table->string('unit_label', 64)->nullable()->after('quantity');
            $table->enum('system_unit', ['g', 'ml', 'piece'])->default('piece')->after('unit_label');
            $table->decimal('conversion_factor', 12, 3)->default(1)->after('system_unit');
            $table->decimal('system_total', 14, 3)->default(0)->after('conversion_factor');
        });
    }

    public function down(): void
    {
        Schema::table('zalo_order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_label', 'system_unit', 'conversion_factor', 'system_total']);
        });
    }
};
