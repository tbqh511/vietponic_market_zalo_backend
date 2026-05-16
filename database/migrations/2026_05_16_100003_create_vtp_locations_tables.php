<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Schema khớp format VTP (PROVINCE_ID, PROVINCE_CODE, PROVINCE_NAME...).
        // Sau sáp nhập tỉnh tháng 7/2025, tin vào ID của VTP — không tự map ID hành chính VN.

        Schema::create('vtp_provinces', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // PROVINCE_ID
            $table->string('code', 16)->nullable();   // PROVINCE_CODE
            $table->string('name');                    // PROVINCE_NAME
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('synced_at')->nullable();
        });

        Schema::create('vtp_districts', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // DISTRICT_ID
            $table->unsignedInteger('province_id');   // PROVINCE_ID
            $table->string('code', 16)->nullable();   // DISTRICT_VALUE
            $table->string('name');                    // DISTRICT_NAME
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('synced_at')->nullable();
            $table->index('province_id');
        });

        Schema::create('vtp_wards', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // WARDS_ID
            $table->unsignedInteger('district_id');   // DISTRICT_ID
            $table->string('name');                    // WARDS_NAME
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('synced_at')->nullable();
            $table->index('district_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vtp_wards');
        Schema::dropIfExists('vtp_districts');
        Schema::dropIfExists('vtp_provinces');
    }
};
