<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('image')->nullable();
            $table->text('address')->nullable();
            // Fallback lat/lng columns for MySQL
            $table->double('location_lat')->nullable();
            $table->double('location_lng')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stations');
    }
};
