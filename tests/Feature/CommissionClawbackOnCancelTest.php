<?php

namespace Tests\Feature;

use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CommissionClawbackOnCancelTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    private Customer $referrer;
    private Customer $referred;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);

        $this->referrer = $this->makeApprovedAffiliate('REFCLAWB1');
        $this->referred = $this->makeCustomer(['referred_by_customer_id' => $this->referrer->id]);
    }

    private function authHeaders(Customer $customer): array
    {
        $token = JWTAuth::claims(['customer_id' => $customer->id])->fromUser($customer);
        return ['Authorization' => "Bearer {$token}"];
    }

    private function makeOrder(string $status = 'pending'): ZaloOrder
    {
        return ZaloOrder::create([
            'customer_id'    => $this->referred->id,
            'status'         => $status,
            'payment_status' => 'cod',
            'payment_method' => 'COD',
            'total'          => 100000,
            'created_at'     => now(),
        ]);
    }

    public function test_customer_cancel_marks_confirmed_commission_as_cancelled(): void
    {
        $order = $this->makeOrder('pending');
        $commission = AffiliateCommission::create([
            'order_id'             => $order->id,
            'referrer_customer_id' => $this->referrer->id,
            'referred_customer_id' => $this->referred->id,
            'order_total'          => 100000,
            'commission_rate'      => 5,
            'commission_amount'    => 5000,
            'status'               => 'confirmed',
        ]);

        $res = $this->withHeaders($this->authHeaders($this->referred))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'customer_other']);
        $res->assertStatus(200);

        $this->assertSame('cancelled', $commission->fresh()->status);
    }

    public function test_admin_cancel_marks_confirmed_commission_as_cancelled(): void
    {
        $order = $this->makeOrder('preparing');
        $commission = AffiliateCommission::create([
            'order_id'             => $order->id,
            'referrer_customer_id' => $this->referrer->id,
            'referred_customer_id' => $this->referred->id,
            'order_total'          => 100000,
            'commission_rate'      => 5,
            'commission_amount'    => 5000,
            'status'               => 'confirmed',
        ]);

        $res = $this->withHeaders(['X-Admin-Secret' => env('ADMIN_API_SECRET')])
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled']);
        $res->assertOk();

        $this->assertSame('cancelled', $commission->fresh()->status);
    }

    public function test_paid_commission_is_not_clawed_back(): void
    {
        $order = $this->makeOrder('pending');
        $commission = AffiliateCommission::create([
            'order_id'             => $order->id,
            'referrer_customer_id' => $this->referrer->id,
            'referred_customer_id' => $this->referred->id,
            'order_total'          => 100000,
            'commission_rate'      => 5,
            'commission_amount'    => 5000,
            'status'               => 'paid',
            'paid_at'              => now(),
        ]);

        $res = $this->withHeaders($this->authHeaders($this->referred))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'customer_other']);
        $res->assertStatus(200);

        // Paid commission phải giữ nguyên — đã chi trả qua payout.
        $this->assertSame('paid', $commission->fresh()->status);
    }

    public function test_no_commission_no_error(): void
    {
        $order = $this->makeOrder('pending');
        // Không tạo commission

        $res = $this->withHeaders($this->authHeaders($this->referred))
            ->postJson("/api/orders/{$order->id}/cancel", ['reason_code' => 'customer_other']);
        $res->assertStatus(200);

        $this->assertSame(0, AffiliateCommission::where('order_id', $order->id)->count());
    }
}
