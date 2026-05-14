<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('farm_name')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_partners');
    }
};
