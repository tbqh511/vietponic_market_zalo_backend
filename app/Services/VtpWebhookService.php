<?php

namespace App\Services;

use App\Models\VtpTrackingEvent;
use App\Models\ZaloOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Xử lý 1 webhook event từ VTP. Trách nhiệm:
 *   1. Dedup theo (order_id, status_code, status_at) — bypass nếu trùng.
 *   2. Bypass nếu order đã ở terminal status (101/107/201/501/503/504).
 *   3. Insert event vào zalo_vtp_tracking_events.
 *   4. Update snapshot trên zalo_deliveries.
 *   5. Map VTP status → ZaloOrder.status, fire side-effects (release stock + refund khi cancel).
 */
class VtpWebhookService
{
    private const TERMINAL_VTP_CODES = [101, 107, 201, 501, 503, 504];

    public function __construct(
        private StockService $stockService,
        private RefundService $refundService,
    ) {}

    public function processEvent(ZaloOrder $order, array $data): void
    {
        $statusCode = (int) ($data['ORDER_STATUS'] ?? 0);
        if ($statusCode <= 0) {
            Log::channel('viettelpost')->warning('[Webhook] ORDER_STATUS không hợp lệ', ['order_id' => $order->id, 'data' => $data]);
            return;
        }

        $statusAt = $this->parseVtpDate((string) ($data['ORDER_STATUSDATE'] ?? ''));
        if (!$statusAt) {
            Log::channel('viettelpost')->warning('[Webhook] ORDER_STATUSDATE không parse được', [
                'order_id' => $order->id,
                'raw'      => $data['ORDER_STATUSDATE'] ?? null,
            ]);
            return;
        }

        // 1. Dedup
        $exists = VtpTrackingEvent::where('order_id', $order->id)
            ->where('status_code', $statusCode)
            ->where('status_at', $statusAt)
            ->exists();
        if ($exists) {
            Log::channel('viettelpost')->info('[Webhook] Bypass — duplicate event', [
                'order_id' => $order->id, 'status_code' => $statusCode, 'status_at' => $statusAt->toDateTimeString(),
            ]);
            return;
        }

        // 2. Skip terminal
        $order->loadMissing('delivery');
        $currentVtpCode = (int) ($order->delivery->vtp_status_code ?? 0);
        if (in_array($currentVtpCode, self::TERMINAL_VTP_CODES, true)) {
            Log::channel('viettelpost')->info('[Webhook] Bypass — order ở terminal status', [
                'order_id' => $order->id, 'current' => $currentVtpCode, 'new' => $statusCode,
            ]);
            return;
        }

        DB::transaction(function () use ($order, $data, $statusCode, $statusAt) {
            // 3. Insert event
            VtpTrackingEvent::create([
                'order_id'         => $order->id,
                'vtp_order_number' => $data['ORDER_NUMBER'] ?? null,
                'status_code'      => $statusCode,
                'status_name'      => (string) ($data['STATUS_NAME'] ?? ''),
                'location'         => $data['LOCATION_CURRENTLY'] ?? ($data['LOCALION_CURRENTLY'] ?? null),  // VTP doc có typo "LOCALION"
                'note'             => $data['NOTE'] ?? null,
                'employee_name'    => $data['EMPLOYEE_NAME'] ?? null,
                'employee_phone'   => $data['EMPLOYEE_PHONE'] ?? null,
                'reason_code'      => isset($data['REASON_CODE']) ? (string) $data['REASON_CODE'] : null,
                'is_returning'     => (bool) ($data['IS_RETURNING'] ?? false),
                'status_at'        => $statusAt,
                'raw_payload'      => $data,
                'created_at'       => now(),
            ]);

            // 4. Update delivery snapshot
            $location = $data['LOCATION_CURRENTLY'] ?? ($data['LOCALION_CURRENTLY'] ?? null);
            $order->delivery()->update([
                'vtp_order_number' => $data['ORDER_NUMBER'] ?? $order->delivery->vtp_order_number,
                'vtp_status_code'  => (string) $statusCode,
                'vtp_status_name'  => (string) ($data['STATUS_NAME'] ?? ''),
                'vtp_location'     => $location,
                'vtp_status_at'    => $statusAt,
                'vtp_is_returning' => (bool) ($data['IS_RETURNING'] ?? false),
            ]);

            // 5. Map + update order status
            $newStatus = $this->mapStatus($statusCode);
            if ($newStatus && $newStatus !== $order->status) {
                $previousStatus = $order->status;
                $updates = ['status' => $newStatus];
                // Farm Partner Hub: chốt delivered_at khi chuyển sang 'delivered'.
                // Dùng $statusAt từ VTP (chính xác hơn now() vì webhook có thể bị
                // queue/retry). Chỉ set một lần — nếu đã có giá trị thì giữ nguyên.
                if ($newStatus === 'delivered' && empty($order->delivered_at)) {
                    $updates['delivered_at'] = $statusAt;
                }
                $order->update($updates);
                $this->handleStatusSideEffects($order, $previousStatus, $newStatus, $statusCode);

                Log::channel('viettelpost')->info('[Webhook] Status updated', [
                    'order_id' => $order->id,
                    'from'     => $previousStatus,
                    'to'       => $newStatus,
                    'vtp_code' => $statusCode,
                ]);
            }
        });
    }

    /**
     * Map mã trạng thái VTP → BackendOrderStatus.
     *  - Trả null nếu không map → caller giữ nguyên status (chỉ log event).
     */
    public function mapStatus(int $code): ?string
    {
        return match (true) {
            in_array($code, [103, 104], true)                                      => 'confirmed',
            in_array($code, [200, 202], true)                                      => 'preparing',
            in_array($code, [300, 400, 500, 506, 507, 508, 509, 515, 550], true)   => 'delivering',
            $code === 501                                                          => 'delivered',
            in_array($code, [101, 107, 201, 503, 504, 505], true)                  => 'cancelled',
            default                                                                => null,
        };
    }

    private function handleStatusSideEffects(ZaloOrder $order, string $prev, string $new, int $vtpCode): void
    {
        if ($new !== 'cancelled' || $prev === 'cancelled') {
            return;
        }

        $reason = match ($vtpCode) {
            504, 505 => "Chuyển hoàn người gửi (VTP {$vtpCode})",
            101      => 'VTP huỷ lấy hàng (TT 101)',
            107      => 'Đối tác yêu cầu huỷ (TT 107)',
            201      => 'Viettel Post huỷ đơn hàng (TT 201)',
            503      => 'Tiêu huỷ theo yêu cầu khách hàng (TT 503)',
            default  => "VTP huỷ ({$vtpCode})",
        };

        $order->update([
            'cancelled_at'        => now(),
            'cancelled_by'        => 'system',
            'cancellation_reason' => mb_substr($reason, 0, 500),
        ]);

        try {
            $this->stockService->releaseReservation($order->id);
        } catch (\Throwable $e) {
            Log::channel('viettelpost')->error('[Webhook] releaseReservation FAIL', [
                'order_id' => $order->id, 'message' => $e->getMessage(),
            ]);
        }

        try {
            $this->refundService->processCancellationRefund($order, 'system');
        } catch (\Throwable $e) {
            Log::channel('viettelpost')->error('[Webhook] processCancellationRefund FAIL', [
                'order_id' => $order->id, 'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse VTP date format "10/11/2025 11: 07: 16" (có khoảng trắng dư sau dấu ":").
     * Cũng accept "10/11/2025 11:07:16" (no extra space).
     * Trả null nếu không parse được.
     */
    private function parseVtpDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        // Loại bỏ khoảng trắng dư trong time part: "11: 07: 16" → "11:07:16"
        $cleaned = preg_replace('/:\s+/', ':', $raw);

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s'] as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $cleaned);
                if ($parsed) return $parsed;
            } catch (\Throwable $e) {
                // try next format
            }
        }

        Log::channel('viettelpost')->warning('[Webhook] parseVtpDate failed', ['raw' => $raw, 'cleaned' => $cleaned]);
        return null;
    }
}
