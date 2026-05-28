<?php

namespace Tests\Feature;

use App\Events\OrderPaymentSucceeded;
use App\Jobs\SendZaloNotification;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloDelivery;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Assert job SendZaloNotification được dispatch đúng ở 4 điểm trigger với payload
 * đúng (eventType + order id). Dùng Bus::fake() để chặn job chạy thật.
 */
class ZaloNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private const VTP_TOKEN = 'test-vtp-webhook-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'zalo_notify_enabled'], ['data' => '1']);
        config(['viettelpost.webhook_token' => self::VTP_TOKEN]);
        config(['viettelpost.webhook_ip_whitelist' => []]);

        $this->customer = Customer::create([
            'name' => 'Khách Test', 'email' => 'kh@test', 'firebase_id' => 'fb-test',
            'mobile' => '0905123456', 'logintype' => 'zalo', 'isActive' => 1,
        ]);
    }

    private function makeOrder(string $status = 'pending', array $override = []): ZaloOrder
    {
        return ZaloOrder::create(array_merge([
            'customer_id'   => $this->customer->id,
            'status'        => $status,
            'payment_status'=> 'pending',
            'total'         => 150000,
            'created_at'    => now(),
        ], $override));
    }

    private function jwtHeaders(): array
    {
        $token = JWTAuth::claims(['customer_id' => $this->customer->id])->fromUser($this->customer);
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_payment_succeeded_dispatches_paid_notification(): void
    {
        Bus::fake();
        $order = $this->makeOrder();

        // SendOrderNotification là ShouldQueue → gọi handle() trực tiếp để kiểm tra
        // nó enqueue đúng job paid (Bus::fake không chạy queued listener internals).
        (new \App\Listeners\SendOrderNotification())->handle(new OrderPaymentSucceeded($order->id));

        Bus::assertDispatched(SendZaloNotification::class, function ($job) use ($order) {
            return $this->jobEvent($job) === 'paid' && $this->jobOrderId($job) === $order->id;
        });
    }

    public function test_payment_listener_registered_for_event(): void
    {
        $provider = new \App\Providers\EventServiceProvider(app());
        $this->assertContains(
            \App\Listeners\SendOrderNotification::class,
            $provider->listens()[OrderPaymentSucceeded::class] ?? [],
            'SendOrderNotification phải được đăng ký cho OrderPaymentSucceeded'
        );
    }

    public function test_admin_status_change_dispatches_status_changed(): void
    {
        Bus::fake();
        $order = $this->makeOrder('pending');

        $this->withHeaders(['X-Admin-Secret' => env('ADMIN_API_SECRET')])
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk();

        Bus::assertDispatched(SendZaloNotification::class, function ($job) use ($order) {
            return $this->jobEvent($job) === 'status_changed' && $this->jobOrderId($job) === $order->id;
        });
    }

    public function test_admin_cancel_dispatches_cancelled(): void
    {
        Bus::fake();
        $order = $this->makeOrder('pending');

        $this->withHeaders(['X-Admin-Secret' => env('ADMIN_API_SECRET')])
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        Bus::assertDispatched(SendZaloNotification::class, fn ($job) => $this->jobEvent($job) === 'cancelled');
    }

    public function test_no_dispatch_when_status_unchanged(): void
    {
        Bus::fake();
        $order = $this->makeOrder('preparing');

        $this->withHeaders(['X-Admin-Secret' => env('ADMIN_API_SECRET')])
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk();

        Bus::assertNotDispatched(SendZaloNotification::class);
    }

    public function test_customer_cancel_dispatches_cancelled(): void
    {
        Bus::fake();
        $order = $this->makeOrder('pending');

        $this->withHeaders($this->jwtHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", ['reason' => 'Đổi ý'])
            ->assertOk();

        Bus::assertDispatched(SendZaloNotification::class, function ($job) use ($order) {
            return $this->jobEvent($job) === 'cancelled' && $this->jobOrderId($job) === $order->id;
        });
    }

    public function test_vtp_webhook_dispatches_shipping(): void
    {
        Bus::fake();
        $order = $this->makeOrder('confirmed', [
            'payment_status' => 'success', 'payment_method' => 'BANK',
            'subtotal' => 120000, 'shipping_fee' => 30000,
        ]);
        ZaloDelivery::create([
            'order_id' => $order->id, 'type' => 'shipping', 'address' => '123',
            'name' => 'Nguyễn A', 'phone' => '0912', 'province_id' => 1,
        ]);

        // ORDER_STATUS=200 → map 'preparing' (khác 'confirmed' hiện tại) → dispatch shipping.
        $this->postJson('/api/viettelpost/webhook', [
            'TOKEN' => self::VTP_TOKEN,
            'DATA'  => [
                'ORDER_NUMBER'     => 'VTP123456',
                'ORDER_REFERENCE'  => 'VPN' . $order->id,
                'ORDER_STATUS'     => 200,
                'STATUS_NAME'      => 'Đã lấy hàng',
                'ORDER_STATUSDATE' => '17/05/2026 10:00:00',
                'IS_RETURNING'     => false,
            ],
        ])->assertStatus(200);

        Bus::assertDispatched(SendZaloNotification::class, function ($job) use ($order) {
            return $this->jobEvent($job) === 'shipping' && $this->jobOrderId($job) === $order->id;
        });
    }

    // ── helpers đọc protected props của job qua reflection ───────────────────

    private function jobEvent(SendZaloNotification $job): string
    {
        return $this->readProp($job, 'eventType');
    }

    private function jobOrderId(SendZaloNotification $job): int
    {
        return $this->readProp($job, 'orderId');
    }

    private function readProp(object $obj, string $name)
    {
        $ref = new \ReflectionProperty($obj, $name);
        $ref->setAccessible(true);
        return $ref->getValue($obj);
    }
}
