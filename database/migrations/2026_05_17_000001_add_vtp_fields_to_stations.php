<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->unsignedInteger('vtp_province_id')->nullable()->after('lng');
            $table->unsignedInteger('vtp_district_id')->nullable()->after('vtp_province_id');
            $table->unsignedInteger('vtp_ward_id')->nullable()->after('vtp_district_id');
            $table->string('vtp_address', 500)->nullable()->after('vtp_ward_id');
            $table->index('vtp_province_id', 'stations_vtp_province_idx');
        });
    }

    public function down()
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropIndex('stations_vtp_province_idx');
            $table->dropColumn(['vtp_province_id', 'vtp_district_id', 'vtp_ward_id', 'vtp_address']);
        });
    }
};
