<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('received_at');
            $table->string('cancelled_by', 16)->nullable()->after('cancelled_at');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');

            $table->string('refund_status', 32)->nullable()->after('cancellation_reason');
            $table->decimal('refund_amount', 15, 2)->nullable()->after('refund_status');
            $table->string('refund_method', 32)->nullable()->after('refund_amount');
            $table->string('refund_transaction_id', 128)->nullable()->after('refund_method');
            $table->string('refund_provider_id', 128)->nullable()->after('refund_transaction_id');
            $table->timestamp('refunded_at')->nullable()->after('refund_provider_id');
            $table->text('refund_note')->nullable()->after('refunded_at');

            $table->index('refund_status');
            $table->index('refund_transaction_id');
        });
    }

    public function down()
    {
        Schema::table('zalo_orders', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropIndex(['refund_transaction_id']);
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
                'refund_status',
                'refund_amount',
                'refund_method',
                'refund_transaction_id',
                'refund_provider_id',
                'refunded_at',
                'refund_note',
            ]);
        });
    }
};
