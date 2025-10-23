<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('type'); // shipping | pickup
            $table->string('alias')->nullable();
            $table->text('address')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('station_id')->nullable();
            $table->text('station_image')->nullable();
            $table->double('location_lat')->nullable();
            $table->double('location_lng')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('station_id')->references('id')->on('stations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
};
