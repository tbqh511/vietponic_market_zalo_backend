<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->unsignedInteger('province_id')->nullable()->after('phone');
            $table->unsignedInteger('district_id')->nullable()->after('province_id');
            $table->unsignedInteger('ward_id')->nullable()->after('district_id');
            // Snapshot tên để hiển thị lại đơn cũ kể cả khi VTP đổi tên hành chính
            $table->string('province_name')->nullable()->after('ward_id');
            $table->string('district_name')->nullable()->after('province_name');
            $table->string('ward_name')->nullable()->after('district_name');
        });
    }

    public function down()
    {
        Schema::table('zalo_deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'province_id', 'district_id', 'ward_id',
                'province_name', 'district_name', 'ward_name',
            ]);
        });
    }
};
