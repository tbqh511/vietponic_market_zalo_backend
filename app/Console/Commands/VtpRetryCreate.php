<?php

namespace App\Console\Commands;

use App\Models\ZaloOrder;
use App\Services\VtpOrderService;
use Illuminate\Console\Command;

/**
 * Retry tạo đơn VTP cho các order shipping chưa có vtp_order_number.
 *
 * Dùng khi:
 *  - VTP API down lúc checkout (đơn Zalo đã tạo, chỉ thiếu phần VTP)
 *  - Admin đã sửa địa chỉ/dịch vụ → cần tạo lại đơn VTP
 *
 * Usage:
 *   php artisan vtp:retry-create 123          # retry order #123
 *   php artisan vtp:retry-create --all        # retry tất cả order shipping chưa có vtp_order_number (sau bao giờ?)
 */
class VtpRetryCreate extends Command
{
    protected $signature = 'vtp:retry-create
        {order_id? : ID đơn hàng cần retry (bỏ qua nếu dùng --all)}
        {--all : Retry tất cả đơn shipping chưa có vtp_order_number trong 7 ngày gần nhất}';

    protected $description = 'Tạo lại đơn VTP cho các order chưa có vtp_order_number';

    public function handle(VtpOrderService $svc): int
    {
        if ($this->option('all')) {
            $orders = ZaloOrder::with(['items', 'delivery'])
                ->whereHas('delivery', function ($q) {
                    $q->where('type', 'shipping')->whereNull('vtp_order_number');
                })
                ->where('created_at', '>=', now()->subDays(7))
                ->whereNotIn('status', ['cancelled'])
                ->get();

            if ($orders->isEmpty()) {
                $this->info('Không có đơn shipping nào cần retry.');
                return self::SUCCESS;
            }

            $this->info("Đang retry {$orders->count()} đơn...");
            foreach ($orders as $order) {
                $this->processOne($order, $svc);
            }
            return self::SUCCESS;
        }

        $id = $this->argument('order_id');
        if (!$id) {
            $this->error('Thiếu order_id (hoặc dùng --all).');
            return self::FAILURE;
        }

        $order = ZaloOrder::with(['items', 'delivery'])->find($id);
        if (!$order) {
            $this->error("Không tìm thấy order #{$id}.");
            return self::FAILURE;
        }

        return $this->processOne($order, $svc) ? self::SUCCESS : self::FAILURE;
    }

    private function processOne(ZaloOrder $order, VtpOrderService $svc): bool
    {
        try {
            $data = $svc->dispatchOrderToVtp($order);
            $this->info("✓ Order #{$order->id} → VTP {$data['ORDER_NUMBER']}");
            return true;
        } catch (\Throwable $e) {
            $this->error("✗ Order #{$order->id}: {$e->getMessage()}");
            return false;
        }
    }
}
