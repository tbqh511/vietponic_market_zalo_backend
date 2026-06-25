<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng farm_role từ ('owner','staff') sang 4 vai trò cụ thể:
 *
 *   owner   — chủ farm, nhận payout, nhiều người/farm nhưng 1 TK ngân hàng
 *             (canonical: farms.owner_customer_id)
 *   admin   — quyền vận hành như owner (confirm order, assign, handoff),
 *             không xem payout tài chính
 *   packer  — nhân viên đóng gói (claim / start-packing / confirm-packed)
 *   shipper — nhân viên giao hàng nội bộ (pickup / deliver)
 *
 * Backfill: staff cũ → packer (giảm quyền an toàn; admin gán lại thủ công nếu cần).
 * transferOwnership: chủ cũ sẽ thành admin (giữ quyền vận hành) thay vì staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE customers MODIFY COLUMN farm_role ENUM('owner','admin','packer','shipper') NULL");
        } else {
            // SQLite 3.35+ hỗ trợ DROP COLUMN. Xoá cột cũ rồi thêm lại với
            // CHECK mới — đơn giản hơn table-recreation, tránh conflict với DBAL.
            DB::statement('ALTER TABLE customers DROP COLUMN farm_role');
            DB::statement("ALTER TABLE customers ADD COLUMN farm_role TEXT CHECK (farm_role IN ('owner','admin','packer','shipper'))");
        }

        // Backfill: staff cũ → packer.
        DB::table('customers')
            ->whereNotNull('farm_id')
            ->where('farm_role', 'staff')
            ->update(['farm_role' => 'packer']);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        // Rollback: đưa tất cả non-owner về 'staff' trước khi thu hẹp enum.
        DB::table('customers')
            ->whereNotNull('farm_id')
            ->whereIn('farm_role', ['admin', 'packer', 'shipper'])
            ->update(['farm_role' => 'staff']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE customers MODIFY COLUMN farm_role ENUM('owner','staff') NULL");
        } else {
            DB::statement('ALTER TABLE customers DROP COLUMN farm_role');
            DB::statement("ALTER TABLE customers ADD COLUMN farm_role TEXT CHECK (farm_role IN ('owner','staff'))");
        }
    }
};
