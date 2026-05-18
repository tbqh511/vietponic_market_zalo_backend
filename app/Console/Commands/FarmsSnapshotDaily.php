<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\FarmPayout;
use App\Models\FarmStockBatch;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * farms:snapshot-daily — cron chốt số liệu mỗi ngày cho Farm Partner Hub.
 *
 * Hai việc chính:
 *   1. Expire các batch quá hạn (status='active' & expire_date < today)
 *      → chuyển status='expired'. Tránh tồn kho ảo ở dashboard.
 *   2. Cập nhật accrued payout cho mỗi farm active theo payment_cycle
 *      (weekly/biweekly/monthly). Mỗi farm có 1 row FarmPayout
 *      status='draft' cho kỳ hiện tại — cron sẽ updateOrCreate row đó với
 *      gross_revenue/total_sold tích lũy đến ngày chạy.
 *
 * Doanh thu chỉ tính từ order_items thuộc đơn ở trạng thái 'delivering'
 * hoặc 'delivered' (đã giao thành công hoặc đang giao — đã xác nhận bán).
 *
 * Chạy:
 *   php artisan farms:snapshot-daily                 # chạy cho today()
 *   php artisan farms:snapshot-daily --date=2026-05-18
 *
 * Schedule: hằng ngày 23:30 (xem Console\Kernel).
 */
class FarmsSnapshotDaily extends Command
{
    protected $signature = 'farms:snapshot-daily {--date= : Ngày snapshot (YYYY-MM-DD), mặc định today}';

    protected $description = 'Chốt số liệu daily: expire batch quá hạn + tính accrued payout cho từng farm';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : Carbon::today();

        $this->info("Snapshot ngày {$date->toDateString()}");

        // 1. Expire các batch quá hạn (chưa expire trước đó).
        $expired = FarmStockBatch::where('status', 'active')
            ->whereNotNull('expire_date')
            ->whereDate('expire_date', '<', $date)
            ->update(['status' => 'expired']);
        $this->info("Đã expire {$expired} batch quá hạn");

        // 2. Update accrued payout cho từng farm đang active.
        $farmCount = 0;
        foreach (Farm::active()->get() as $farm) {
            $this->updateAccrued($farm, $date);
            $farmCount++;
        }
        $this->info("Đã cập nhật accrued payout cho {$farmCount} farm");

        return self::SUCCESS;
    }

    /**
     * Tính accrued cho kỳ hiện tại của 1 farm và upsert FarmPayout draft.
     */
    private function updateAccrued(Farm $farm, Carbon $date): void
    {
        [$periodStart, $periodEnd] = $this->calculatePeriod($farm, $date);

        // Doanh thu tích lũy: SUM(cost_price_snapshot * quantity) cho các
        // order_items thuộc farm, đơn ở trạng thái đã/đang giao trong period.
        // cost_price_snapshot = giá vốn chốt tại lúc tạo order (FEFO).
        $revenue = (float) DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.farm_id', $farm->id)
            ->whereBetween(DB::raw('DATE(o.created_at)'), [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->whereIn('o.status', ['delivering', 'delivered'])
            ->sum(DB::raw('oi.cost_price_snapshot * oi.quantity'));

        $totalSold = (float) DB::table('zalo_order_items as oi')
            ->join('zalo_orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.farm_id', $farm->id)
            ->whereBetween(DB::raw('DATE(o.created_at)'), [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->whereIn('o.status', ['delivering', 'delivered'])
            ->sum('oi.quantity');

        // Tìm theo farm+period không có status — nếu admin đã chuyển sang pending/paid,
        // không tạo duplicate. Chỉ cập nhật khi status vẫn là draft (chưa lock).
        $payout = FarmPayout::firstOrNew([
            'farm_id'      => $farm->id,
            'period_start' => $periodStart->toDateString(),
            'period_end'   => $periodEnd->toDateString(),
        ]);

        // Không ghi đè payout đã được admin chốt (pending/paid/cancelled).
        if ($payout->exists && $payout->status !== 'draft') {
            return;
        }

        $payout->gross_revenue = $revenue;
        $payout->total_sold    = $totalSold;
        $payout->adjustment    = $payout->adjustment ?? 0;
        $payout->net_payout    = $revenue + ($payout->adjustment ?? 0);
        $payout->status        = 'draft';
        $payout->save();
    }

    /**
     * Xác định kỳ thanh toán dựa vào farm.payment_cycle.
     *
     * - weekly  : tuần chứa $date (Mon → Sun)
     * - biweekly: 2 tuần ăn về tuần chứa $date (start tuần trước → end tuần hiện tại)
     * - monthly : tháng chứa $date
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function calculatePeriod(Farm $farm, Carbon $date): array
    {
        return match ($farm->payment_cycle) {
            'weekly'   => [$date->copy()->startOfWeek(),               $date->copy()->endOfWeek()],
            'biweekly' => [$date->copy()->startOfWeek()->subWeek(),    $date->copy()->endOfWeek()],
            'monthly'  => [$date->copy()->startOfMonth(),              $date->copy()->endOfMonth()],
            default    => [$date->copy()->startOfMonth(),              $date->copy()->endOfMonth()],
        };
    }
}
