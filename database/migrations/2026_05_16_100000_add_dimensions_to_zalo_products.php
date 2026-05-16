<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_products', function (Blueprint $table) {
            // gam — default 500g cho rau thuỷ canh
            $table->unsignedInteger('weight')->default(500)->after('detail');
            // cm — default 20x15x10 cho hộp rau thuỷ canh
            $table->unsignedSmallInteger('length')->default(20)->after('weight');
            $table->unsignedSmallInteger('width')->default(15)->after('length');
            $table->unsignedSmallInteger('height')->default(10)->after('width');
        });
    }

    public function down()
    {
        Schema::table('zalo_products', function (Blueprint $table) {
            $table->dropColumn(['weight', 'length', 'width', 'height']);
        });
    }
};
