<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('affiliate_code', 20)->nullable()->unique();
            $table->unsignedBigInteger('referred_by_customer_id')->nullable()->index();
            $table->enum('affiliate_status', ['pending', 'approved', 'suspended', 'rejected'])->nullable();
            $table->timestamp('affiliate_approved_at')->nullable();
            $table->string('affiliate_bank_name')->nullable();
            $table->string('affiliate_bank_account')->nullable();
            $table->string('affiliate_bank_holder')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['affiliate_code']);
            $table->dropIndex(['referred_by_customer_id']);
            $table->dropColumn([
                'affiliate_code',
                'referred_by_customer_id',
                'affiliate_status',
                'affiliate_approved_at',
                'affiliate_bank_name',
                'affiliate_bank_account',
                'affiliate_bank_holder',
            ]);
        });
    }
};
