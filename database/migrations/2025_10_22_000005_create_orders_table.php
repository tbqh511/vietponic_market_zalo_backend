<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('status');
            $table->string('payment_status');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('received_at')->nullable();
            $table->bigInteger('total');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->index('created_by_user_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
