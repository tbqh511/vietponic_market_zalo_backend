<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('note');
        });
    }

    public function down()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};