<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\ZaloOrder;
use App\Services\ZaloOaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Bắn tin Zalo cho 1 đơn theo sự kiện nghiệp vụ.
 *
 * Kênh: thử OA Message trước (miễn phí, cần follow OA), fallback ZNS theo SĐT.
 * Async qua queue, fail im lặng — KHÔNG throw lỗi nghiệp vụ ra ngoài (thiếu
 * template, thiếu SĐT...) để không retry vô hạn. Chỉ lỗi tạm thời (HTTP) mới để
 * queue retry qua $tries/$backoff.
 *
 * Bật/tắt: Setting 'zalo_notify_enabled' (admin toggle), fallback config
 * services.zalo.notify_enabled.
 *
 * ── ZNS template params (điền template_id thật vào .env sau khi Zalo duyệt) ──
 *   paid       → ['ma_don' => '#123', 'tong_tien' => '150.000đ']
 *   status     → ['ma_don' => '#123', 'trang_thai' => 'Đang chuẩn bị']
 *   cancelled  → ['ma_don' => '#123', 'ly_do' => '...']
 *   shipping   → ['ma_don' => '#123', 'trang_thai' => 'Đang giao']
 */
class SendZaloNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        protected int $orderId,
        protected string $eventType,
        protected array $extra = []
    ) {}

    public function handle(ZaloOaClient $client): void
    {
        if (!$this->notifyEnabled()) {
            return;
        }

        $order = ZaloOrder::with(['customer', 'delivery'])->find($this->orderId);
        if (!$order) {
            Log::channel('zalo_notify')->warning('[Notify] order không tồn tại', ['order_id' => $this->orderId]);
            return;
        }

        $customer = $order->customer;
        if (!$customer) {
            Log::channel('zalo_notify')->warning('[Notify] order không có customer', ['order_id' => $this->orderId]);
            return;
        }

        $templateData = $this->buildTemplateData($order);

        // Kênh OA phụ trước (miễn phí) — chỉ khi khách đã follow OA.
        if ($customer->oa_followed && $customer->firebase_id) {
            $result = $client->sendOaMessage($customer->firebase_id, [
                'text' => $this->buildOaText($templateData),
            ]);
            if ($result['ok']) {
                return;
            }
            // OA fail → rơi xuống ZNS fallback bên dưới.
        }

        // Fallback ZNS theo SĐT.
        $templateId = $this->resolveTemplateId();
        if (!$customer->mobile) {
            Log::channel('zalo_notify')->info('[Notify] bỏ qua: khách không có SĐT và chưa follow OA', [
                'order_id' => $order->id, 'event' => $this->eventType,
            ]);
            return;
        }
        if (!$templateId) {
            Log::channel('zalo_notify')->warning('[Notify] bỏ qua: chưa cấu hình ZNS template', [
                'order_id' => $order->id, 'event' => $this->eventType,
            ]);
            return;
        }

        $client->sendZns($customer->mobile, $templateId, $templateData);
    }

    private function notifyEnabled(): bool
    {
        $row = Setting::where('type', 'zalo_notify_enabled')->value('data');
        if ($row !== null) {
            return (string) $row === '1';
        }

        return (bool) config('services.zalo.notify_enabled', false);
    }

    private function resolveTemplateId(): ?string
    {
        $key = match ($this->eventType) {
            'paid'           => 'zns_template_paid',
            'status_changed' => 'zns_template_status',
            'cancelled'      => 'zns_template_cancelled',
            'shipping'       => 'zns_template_shipping',
            default          => null,
        };

        return $key ? config("services.zalo.{$key}") : null;
    }

    private function buildTemplateData(ZaloOrder $order): array
    {
        $maDon = '#' . $order->id;
        $data  = ['ma_don' => $maDon];

        switch ($this->eventType) {
            case 'paid':
                $data['tong_tien'] = $this->formatVnd($order->total);
                break;
            case 'status_changed':
            case 'shipping':
                $data['trang_thai'] = $this->statusLabel($this->extra['status'] ?? $order->status);
                break;
            case 'cancelled':
                $data['ly_do'] = (string) ($order->cancellation_reason ?? 'Đơn hàng đã được huỷ');
                break;
        }

        return $data;
    }

    private function buildOaText(array $data): string
    {
        $maDon = $data['ma_don'] ?? '';

        return match ($this->eventType) {
            'paid'           => "Đơn hàng {$maDon} đã thanh toán thành công. Tổng tiền: " . ($data['tong_tien'] ?? '') . ". Cảm ơn bạn đã mua hàng!",
            'status_changed' => "Đơn hàng {$maDon} cập nhật trạng thái: " . ($data['trang_thai'] ?? '') . '.',
            'shipping'       => "Đơn hàng {$maDon} đang được vận chuyển: " . ($data['trang_thai'] ?? '') . '.',
            'cancelled'      => "Đơn hàng {$maDon} đã được huỷ. " . ($data['ly_do'] ?? ''),
            default          => "Cập nhật đơn hàng {$maDon}.",
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending'    => 'Chờ xác nhận',
            'confirmed'  => 'Đã xác nhận',
            'preparing'  => 'Đang chuẩn bị',
            'delivering' => 'Đang giao',
            'delivered'  => 'Đã giao',
            'cancelled'  => 'Đã huỷ',
            default      => (string) $status,
        };
    }

    private function formatVnd($amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . 'đ';
    }
}
