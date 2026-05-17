<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zalo_vtp_tracking_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('vtp_order_number', 64)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('status_name');
            $table->string('location')->nullable();
            $table->text('note')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_phone', 32)->nullable();
            $table->string('reason_code', 16)->nullable();
            $table->boolean('is_returning')->default(false);
            $table->timestamp('status_at');
            $table->json('raw_payload');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')->references('id')->on('zalo_orders')->onDelete('cascade');
            $table->index('vtp_order_number');
            $table->unique(['order_id', 'status_code', 'status_at'], 'uniq_order_status_time');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zalo_vtp_tracking_events');
    }
};
