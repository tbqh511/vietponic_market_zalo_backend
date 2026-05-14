<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_partner_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('farm_partner_id')->nullable();
            $table->enum('action', ['created', 'activated', 'deactivated']);
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_partner_logs');
    }
};
