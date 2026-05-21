<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            // Snapshot số tiền đã giảm thực tế (subtotal + shipping)
            $table->unsignedInteger('discount_amount');
            $table->dateTime('redeemed_at');
            $table->timestamps();

            $table->unique(['voucher_id', 'order_id']);
            $table->index(['customer_id', 'voucher_id']);

            $table->foreign('order_id')->references('id')->on('zalo_orders')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('voucher_redemptions');
    }
};
