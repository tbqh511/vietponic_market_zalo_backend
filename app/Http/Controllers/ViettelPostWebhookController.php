<?php

namespace App\Http\Controllers;

use App\Models\ZaloOrder;
use App\Services\VtpWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook endpoint nhận thông báo trạng thái đơn từ ViettelPost.
 *
 * Doc: VTP yêu cầu phản hồi HTTP 200 trong < 1 giây. Nếu không nhận 200 → retry tối đa 5 lần.
 * Vì vậy mọi nhánh xử lý đều trả 200 (kể cả error) — chỉ log để debug, không khiến VTP retry vô ích.
 *
 * Verify: TOKEN trong body (so với env VTP_WEBHOOK_TOKEN) + optional IP whitelist.
 */
class ViettelPostWebhookController extends Controller
{
    public function __construct(
        private VtpWebhookService $service,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::channel('viettelpost')->info('[Webhook] received', [
            'ip'      => $request->ip(),
            'payload' => $request->all(),
        ]);

        // 1. Verify TOKEN
        $expectedToken = (string) config('viettelpost.webhook_token', '');
        $receivedToken = (string) $request->input('TOKEN', '');
        if ($expectedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
            Log::channel('viettelpost')->warning('[Webhook] Invalid TOKEN', [
                'received_len' => strlen($receivedToken),
                'expected_set' => $expectedToken !== '',
            ]);
            return response()->json(['error' => true, 'message' => 'Invalid token'], 200);
        }

        // 2. Optional IP whitelist
        $whitelist = (array) config('viettelpost.webhook_ip_whitelist', []);
        if (!empty($whitelist) && !in_array($request->ip(), $whitelist, true)) {
            Log::channel('viettelpost')->warning('[Webhook] IP not whitelisted', ['ip' => $request->ip()]);
            return response()->json(['error' => true, 'message' => 'IP not allowed'], 200);
        }

        // 3. Extract & validate DATA
        $data = $request->input('DATA');
        if (!is_array($data) || empty($data['ORDER_REFERENCE']) || empty($data['ORDER_STATUS'])) {
            Log::channel('viettelpost')->warning('[Webhook] Missing DATA fields', ['data' => $data]);
            return response()->json(['error' => true, 'message' => 'Missing DATA fields'], 200);
        }

        // 4. Find order theo ORDER_REFERENCE (VPN{id}) hoặc ORDER_NUMBER
        $orderId = $this->parseOrderId((string) $data['ORDER_REFERENCE']);
        $order = null;
        if ($orderId) {
            $order = ZaloOrder::with('delivery')->find($orderId);
        }
        // Fallback: tìm theo vtp_order_number nếu reference không parse được
        if (!$order && !empty($data['ORDER_NUMBER'])) {
            $order = ZaloOrder::with('delivery')
                ->whereHas('delivery', fn($q) => $q->where('vtp_order_number', $data['ORDER_NUMBER']))
                ->first();
        }
        if (!$order) {
            Log::channel('viettelpost')->warning('[Webhook] Order not found', [
                'reference'    => $data['ORDER_REFERENCE'] ?? null,
                'order_number' => $data['ORDER_NUMBER'] ?? null,
            ]);
            return response()->json(['error' => false, 'message' => 'Order not found, bypassed'], 200);
        }

        // 5. Delegate to service
        try {
            $this->service->processEvent($order, $data);
        } catch (\Throwable $e) {
            Log::channel('viettelpost')->error('[Webhook] processEvent FAIL', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            // Vẫn trả 200 để VTP không retry
        }

        return response()->json(['error' => false, 'message' => 'OK'], 200);
    }

    /**
     * "VPN123" → 123; "123" → 123; "VTP1234..." → null (sẽ fallback theo ORDER_NUMBER).
     */
    private function parseOrderId(string $reference): ?int
    {
        if (preg_match('/^VPN(\d+)$/i', $reference, $m)) {
            return (int) $m[1];
        }
        if (ctype_digit($reference)) {
            return (int) $reference;
        }
        return null;
    }
}
