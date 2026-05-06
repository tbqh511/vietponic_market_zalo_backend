<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zalo_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->bigInteger('price')->default(0);
            $table->integer('quantity')->default(1);
            $table->string('image')->nullable();
            $table->text('detail')->nullable();
            $table->foreign('order_id')->references('id')->on('zalo_orders')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zalo_order_items');
    }
};
