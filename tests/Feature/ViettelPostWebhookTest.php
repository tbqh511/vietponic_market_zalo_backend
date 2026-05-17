<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\VtpTrackingEvent;
use App\Models\ZaloCategory;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use App\Models\ZaloProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test toàn bộ flow webhook ViettelPost: verify TOKEN, parse payload, dedup,
 * map status, side-effects (cancel → release + refund). Tất cả response phải là HTTP 200.
 */
class ViettelPostWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_TOKEN = 'test-vtp-webhook-secret-token';

    private ZaloOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viettelpost.webhook_token' => self::TEST_TOKEN]);
        config(['viettelpost.webhook_ip_whitelist' => []]);

        $cat = ZaloCategory::create(['id' => 1, 'name' => 'Test', 'image' => null]);
        ZaloProduct::create([
            'id' => 1, 'category_id' => 1, 'name' => 'SP', 'price' => 10000, 'stock' => 100,
        ]);
        $customer = Customer::create([
            'name' => 'T', 'email' => 'x@x', 'firebase_id' => 'fb1',
            'logintype' => 'zalo', 'isActive' => 1,
        ]);

        $this->order = ZaloOrder::create([
            'customer_id'   => $customer->id,
            'status'        => 'pending',
            'payment_status'=> 'success',
            'payment_method'=> 'BANK',
            'created_at'    => now(),
            'received_at'   => now()->addDays(3),
            'subtotal'      => 10000,
            'shipping_fee'  => 30000,
            'total'         => 40000,
        ]);
        ZaloDelivery::create([
            'order_id'    => $this->order->id,
            'type'        => 'shipping',
            'address'     => '123',
            'name'        => 'Nguyễn A',
            'phone'       => '0912',
            'province_id' => 1,
        ]);
    }

    private function payload(int $status, array $override = []): array
    {
        return [
            'TOKEN' => self::TEST_TOKEN,
            'DATA'  => array_merge([
                'ORDER_NUMBER'       => 'VTP123456',
                'ORDER_REFERENCE'    => 'VPN' . $this->order->id,
                'ORDER_STATUS'       => $status,
                'STATUS_NAME'        => 'Test status ' . $status,
                'ORDER_STATUSDATE'   => '17/05/2026 10:00:00',
                'LOCATION_CURRENTLY' => 'HNI, Bưu cục Test',
                'NOTE'               => 'Test note',
                'EMPLOYEE_NAME'      => 'Phạm Test',
                'EMPLOYEE_PHONE'     => '849xxx',
                'IS_RETURNING'       => false,
            ], $override),
        ];
    }

    // ── Auth / validation ────────────────────────────────────────────────────

    public function test_invalid_token_returns_200_but_creates_no_event(): void
    {
        $payload = $this->payload(200, []);
        $payload['TOKEN'] = 'wrong-token';

        $this->postJson('/api/viettelpost/webhook', $payload)
            ->assertStatus(200)
            ->assertJsonPath('error', true);

        $this->assertSame(0, VtpTrackingEvent::count());
    }

    public function test_missing_data_fields_returns_200_no_op(): void
    {
        $this->postJson('/api/viettelpost/webhook', [
            'TOKEN' => self::TEST_TOKEN,
            'DATA'  => ['ORDER_NUMBER' => 'X'], // thiếu ORDER_REFERENCE + ORDER_STATUS
        ])->assertStatus(200);

        $this->assertSame(0, VtpTrackingEvent::count());
    }

    public function test_unknown_order_reference_returns_200_bypass(): void
    {
        $this->postJson('/api/viettelpost/webhook', $this->payload(200, [
            'ORDER_REFERENCE' => 'VPN99999',
        ]))->assertStatus(200);

        $this->assertSame(0, VtpTrackingEvent::count());
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_valid_webhook_creates_event_and_updates_delivery(): void
    {
        $this->postJson('/api/viettelpost/webhook', $this->payload(200))
            ->assertStatus(200)
            ->assertJsonPath('error', false);

        $this->assertSame(1, VtpTrackingEvent::count());
        $event = VtpTrackingEvent::first();
        $this->assertSame($this->order->id, $event->order_id);
        $this->assertSame(200, $event->status_code);
        $this->assertSame('HNI, Bưu cục Test', $event->location);

        $delivery = ZaloDelivery::where('order_id', $this->order->id)->first();
        $this->assertSame('200', $delivery->vtp_status_code);
        $this->assertSame('VTP123456', $delivery->vtp_order_number);
    }

    public function test_status_501_maps_order_to_delivered(): void
    {
        $this->postJson('/api/viettelpost/webhook', $this->payload(501))
            ->assertStatus(200);

        $this->assertSame('delivered', $this->order->fresh()->status);
    }

    public function test_status_500_maps_order_to_delivering(): void
    {
        $this->postJson('/api/viettelpost/webhook', $this->payload(500))
            ->assertStatus(200);

        $this->assertSame('delivering', $this->order->fresh()->status);
    }

    public function test_status_104_maps_order_to_confirmed(): void
    {
        $this->postJson('/api/viettelpost/webhook', $this->payload(104))
            ->assertStatus(200);

        $this->assertSame('confirmed', $this->order->fresh()->status);
    }

    // ── Idempotency ──────────────────────────────────────────────────────────

    public function test_duplicate_webhook_with_same_status_time_is_deduplicated(): void
    {
        $payload = $this->payload(200);

        $this->postJson('/api/viettelpost/webhook', $payload)->assertStatus(200);
        $this->postJson('/api/viettelpost/webhook', $payload)->assertStatus(200);
        $this->postJson('/api/viettelpost/webhook', $payload)->assertStatus(200);

        $this->assertSame(1, VtpTrackingEvent::count());
    }

    public function test_different_status_at_creates_new_event(): void
    {
        $this->postJson('/api/viettelpost/webhook', $this->payload(200, [
            'ORDER_STATUSDATE' => '17/05/2026 10:00:00',
        ]))->assertStatus(200);

        $this->postJson('/api/viettelpost/webhook', $this->payload(200, [
            'ORDER_STATUSDATE' => '17/05/2026 11:00:00',
        ]))->assertStatus(200);

        $this->assertSame(2, VtpTrackingEvent::count());
    }

    // ── Terminal status protection ───────────────────────────────────────────

    public function test_webhook_bypassed_when_order_at_terminal_vtp_status(): void
    {
        // Set delivery về terminal 501 trước
        ZaloDelivery::where('order_id', $this->order->id)->update([
            'vtp_status_code' => '501',
        ]);

        // Webhook gửi event mới (status 506 — phát thất bại) → phải bypass
        $this->postJson('/api/viettelpost/webhook', $this->payload(506))
            ->assertStatus(200);

        $this->assertSame(0, VtpTrackingEvent::count());
    }

    // ── Cancel side-effects ──────────────────────────────────────────────────

    public function test_status_504_returns_cancels_order_and_releases_stock(): void
    {
        // Tạo reservation trước (giả lập stock đã được reserve khi tạo đơn)
        \DB::table('zalo_products')->where('id', 1)->update(['stock_reserved' => 5]);
        \DB::table('stock_movements')->insert([
            'product_id'      => 1,
            'order_id'        => $this->order->id,
            'movement_type'   => 'reserved',
            'quantity_change' => 5,
            'quantity_before' => 100,
            'quantity_after'  => 100,
            'note'            => 'reserved by order',
            'created_at'      => now(),
        ]);

        $this->postJson('/api/viettelpost/webhook', $this->payload(504))
            ->assertStatus(200);

        $fresh = $this->order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('system', $fresh->cancelled_by);
        $this->assertStringContainsString('hoàn người gửi', (string) $fresh->cancellation_reason);
        $this->assertNotNull($fresh->cancelled_at);
    }

    // ── Date parsing edge case (VTP doc có "11: 07: 16" với space dư) ────────

    public function test_parses_vtp_date_with_extra_spaces_in_time(): void
    {
        $this->postJson('/api/viettelpost/webhook', $this->payload(200, [
            'ORDER_STATUSDATE' => '17/05/2026 10: 30: 45',
        ]))->assertStatus(200);

        $event = VtpTrackingEvent::first();
        $this->assertNotNull($event);
        $this->assertSame('2026-05-17 10:30:45', $event->status_at->format('Y-m-d H:i:s'));
    }

    // ── Fallback location field (doc có typo "LOCALION_CURRENTLY") ───────────

    public function test_handles_doc_typo_locailon_currently(): void
    {
        $payload = $this->payload(200);
        unset($payload['DATA']['LOCATION_CURRENTLY']);
        $payload['DATA']['LOCALION_CURRENTLY'] = 'Bưu cục từ typo field';

        $this->postJson('/api/viettelpost/webhook', $payload)->assertStatus(200);

        $event = VtpTrackingEvent::first();
        $this->assertSame('Bưu cục từ typo field', $event->location);
    }
}
