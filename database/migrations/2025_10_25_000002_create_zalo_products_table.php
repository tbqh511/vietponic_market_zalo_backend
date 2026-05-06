<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zalo_products', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->bigInteger('price')->default(0);
            $table->bigInteger('original_price')->nullable();
            $table->string('image')->nullable();
            $table->text('detail')->nullable();
            $table->foreign('category_id')->references('id')->on('zalo_categories')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zalo_products');
    }
};
