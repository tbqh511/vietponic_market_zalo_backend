<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm farm_id + farm_role vào customers để hỗ trợ Staff/Operator của Farm.
 *
 * Mô hình: 1 farm có 1 owner (nhận payout) + N staff (full quyền thao tác Hub
 * nhưng không nhận tiền). 1 customer chỉ thuộc tối đa 1 farm.
 *
 *   - farm_id: FK tới farms.id, NULL = không thuộc farm nào.
 *   - farm_role: 'owner' | 'staff'. Owner là duy nhất, sync với farms.owner_customer_id.
 *
 * Backfill: với mỗi farm đang có owner_customer_id, set customer đó
 *           farm_id = farm.id, farm_role = 'owner'.
 *
 * Middleware EnsureFarmPartner sau migration sẽ tìm farm theo customers.farm_id
 * thay vì farms.owner_customer_id để cover cả owner lẫn staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('farm_id')->nullable()->after('farm_partner_status');
            $table->enum('farm_role', ['owner', 'staff'])->nullable()->after('farm_id');
            $table->index('farm_id');
            $table->foreign('farm_id')
                ->references('id')->on('farms')
                ->nullOnDelete();
        });

        // Backfill owner cho customer hiện đang là chủ farm.
        // Dùng update qua JOIN — tương thích MySQL. SQLite testing không có
        // farm thật nên backfill rỗng, không lỗi.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE customers c
                INNER JOIN farms f ON f.owner_customer_id = c.id
                SET c.farm_id = f.id, c.farm_role = 'owner'
                WHERE c.role = 'farm_partner'
                  AND c.farm_partner_status = 'approved'
            ");
        } else {
            // SQLite / Postgres fallback: per-row update.
            $rows = DB::table('farms')
                ->whereNotNull('owner_customer_id')
                ->get(['id', 'owner_customer_id']);

            foreach ($rows as $row) {
                DB::table('customers')
                    ->where('id', $row->owner_customer_id)
                    ->where('role', 'farm_partner')
                    ->where('farm_partner_status', 'approved')
                    ->update([
                        'farm_id'   => $row->id,
                        'farm_role' => 'owner',
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['farm_id']);
            $table->dropIndex(['farm_id']);
            $table->dropColumn(['farm_id', 'farm_role']);
        });
    }
};
