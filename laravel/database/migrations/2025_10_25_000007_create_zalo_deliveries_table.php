<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zalo_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('type')->nullable();
            $table->string('alias')->nullable();
            $table->text('address')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('station_id')->nullable();
            $table->string('station_name')->nullable();
            $table->string('station_image')->nullable();
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('lng', 10, 6)->nullable();
            $table->foreign('order_id')->references('id')->on('zalo_orders')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zalo_deliveries');
    }
};
