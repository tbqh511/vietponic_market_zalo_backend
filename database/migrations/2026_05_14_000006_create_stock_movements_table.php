<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('movement_type', [
                'import',
                'export',
                'adjustment',
                'return',
                'damage',
                'reserved',
                'unreserved',
            ]);
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('product_id')
                ->references('id')->on('zalo_products')
                ->onDelete('cascade');

            $table->foreign('order_id')
                ->references('id')->on('zalo_orders')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->index('product_id');
            $table->index('order_id');
            $table->index('movement_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
