<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('referrer_customer_id');
            $table->unsignedBigInteger('referred_customer_id');
            $table->decimal('order_total', 15, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->bigInteger('commission_amount');
            $table->enum('status', ['pending', 'confirmed', 'paid', 'cancelled'])->default('confirmed');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
