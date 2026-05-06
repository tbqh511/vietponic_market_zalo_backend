<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zalo_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_status')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->bigInteger('total')->default(0);
            $table->text('note')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('zalo_orders');
    }
};
