<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng farm_payouts — kỳ thanh toán cho farm theo chu kỳ (weekly/biweekly/monthly).
 *
 * Lifecycle:
 *   draft (cron tích lũy hàng ngày) → pending (khi kết thúc kỳ, chờ chuyển tiền)
 *   → paid (đã chuyển khoản) hoặc cancelled.
 *
 * net_payout = gross_revenue + adjustment (adjustment có thể âm: phạt, đền bù).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_payouts', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('farm_id');

            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('total_sold', 12, 2)->default(0);
            $table->decimal('gross_revenue', 14, 2)->default(0);
            $table->decimal('adjustment', 14, 2)->default(0);
            $table->decimal('net_payout', 14, 2)->default(0);

            $table->enum('status', ['draft', 'pending', 'paid', 'cancelled'])
                ->default('draft');

            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('transaction_ref')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['farm_id', 'status']);
            $table->index(['period_start', 'period_end']);

            $table->foreign('farm_id')
                ->references('id')->on('farms')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_payouts');
    }
};
