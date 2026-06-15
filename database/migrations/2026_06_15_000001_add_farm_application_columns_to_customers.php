<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('farm_application_name', 120)->nullable()->after('farm_bank_holder');
            $table->string('farm_application_address', 200)->nullable()->after('farm_application_name');
            $table->text('farm_application_description')->nullable()->after('farm_application_address');
            $table->timestamp('farm_applied_at')->nullable()->after('farm_application_description');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'farm_application_name',
                'farm_application_address',
                'farm_application_description',
                'farm_applied_at',
            ]);
        });
    }
};
