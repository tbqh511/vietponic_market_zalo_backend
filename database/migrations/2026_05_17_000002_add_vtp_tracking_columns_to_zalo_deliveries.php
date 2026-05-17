<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->string('vtp_order_number', 64)->nullable()->after('ward_name');
            $table->string('vtp_order_reference', 64)->nullable()->after('vtp_order_number');
            $table->string('vtp_status_code', 8)->nullable()->after('vtp_order_reference');
            $table->string('vtp_status_name')->nullable()->after('vtp_status_code');
            $table->string('vtp_location')->nullable()->after('vtp_status_name');
            $table->timestamp('vtp_status_at')->nullable()->after('vtp_location');
            $table->boolean('vtp_is_returning')->default(false)->after('vtp_status_at');
            $table->json('vtp_create_response')->nullable()->after('vtp_is_returning');

            $table->index('vtp_order_number');
            $table->unique('vtp_order_reference');
        });
    }

    public function down()
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->dropUnique(['vtp_order_reference']);
            $table->dropIndex(['vtp_order_number']);
            $table->dropColumn([
                'vtp_order_number',
                'vtp_order_reference',
                'vtp_status_code',
                'vtp_status_name',
                'vtp_location',
                'vtp_status_at',
                'vtp_is_returning',
                'vtp_create_response',
            ]);
        });
    }
};
