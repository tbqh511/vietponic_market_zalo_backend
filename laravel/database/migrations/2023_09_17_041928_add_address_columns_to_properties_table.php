<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->string('street_number')->nullable()->default('');
            $table->string('street_code')->nullable()->default('');
            $table->string('ward_code')->nullable()->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->dropColumn('street_number');
            $table->dropColumn('street_code');
            $table->dropColumn('ward_code');
        });
    }
};