<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_legal_images', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('image');
            $table->bigInteger('propertys_id')->unsigned();
            $table->foreign('propertys_id')->references('id')->on('propertys')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('property_legal_images');
    }
};
