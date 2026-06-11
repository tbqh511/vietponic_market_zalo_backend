<?php

namespace Tests\Unit;

use App\Events\OrderDelivered;
use App\Listeners\RecordAffiliateCommission;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateCommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_rate_from_settings(): void
    {
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '7.5']);
        $this->assertEquals(7.5, RecordAffiliateCommission::resolveCommissionRate());
    }

    public function test_resolves_default_rate_when_missing(): void
    {
        Setting::where('type', 'affiliate_commission_rate')->delete();
        $this->assertEquals(5.0, RecordAffiliateCommission::resolveCommissionRate());
    }

    public function test_commission_amount_is_rounded_to_integer(): void
    {
        $referrer = $this->makeReferrer();
        $referred = $this->makeReferred($referrer->id);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);

        $order = $this->makeOrder($referred->id, 123_456);

        (new RecordAffiliateCommission())->handle(new OrderDelivered($order->id));

        $row = AffiliateCommission::where('order_id', $order->id)->firstOrFail();
        // 5% of 123_456 = 6172.8 → round to 6173
        $this->assertEquals(6173, $row->commission_amount);
        $this->assertEquals('confirmed', $row->status);
    }

    public function test_zero_total_skipped(): void
    {
        $referrer = $this->makeReferrer();
        $referred = $this->makeReferred($referrer->id);
        $order = $this->makeOrder($referred->id, 0);

        (new RecordAffiliateCommission())->handle(new OrderDelivered($order->id));

        $this->assertEquals(0, AffiliateCommission::count());
    }

    public function test_skipped_when_no_referrer(): void
    {
        $referred = Customer::create(array_merge($this->customerDefaults('0900000003'), [
            'name' => 'No Referrer',
        ]));
        $order = $this->makeOrder($referred->id, 100_000);

        (new RecordAffiliateCommission())->handle(new OrderDelivered($order->id));

        $this->assertEquals(0, AffiliateCommission::count());
    }

    public function test_skipped_when_referrer_not_approved(): void
    {
        $referrer = Customer::create(array_merge($this->customerDefaults('0900000004'), [
            'name' => 'Pending Referrer',
            'affiliate_code' => 'PENDING1', 'affiliate_status' => 'pending',
        ]));
        $referred = Customer::create(array_merge($this->customerDefaults('0900000005'), [
            'name' => 'Referred',
            'referred_by_customer_id' => $referrer->id,
        ]));
        $order = $this->makeOrder($referred->id, 100_000);

        (new RecordAffiliateCommission())->handle(new OrderDelivered($order->id));

        $this->assertEquals(0, AffiliateCommission::count());
    }

    public function test_idempotent_on_double_fire(): void
    {
        $referrer = $this->makeReferrer();
        $referred = $this->makeReferred($referrer->id);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);
        $order = $this->makeOrder($referred->id, 200_000);

        (new RecordAffiliateCommission())->handle(new OrderDelivered($order->id));
        (new RecordAffiliateCommission())->handle(new OrderDelivered($order->id));

        $this->assertEquals(1, AffiliateCommission::where('order_id', $order->id)->count());
    }

    private function makeReferrer(): Customer
    {
        return Customer::create(array_merge($this->customerDefaults('0900000001'), [
            'name' => 'Referrer',
            'affiliate_code' => 'REFER001', 'affiliate_status' => 'approved',
            'affiliate_approved_at' => now(),
        ]));
    }

    private function makeReferred(int $referrerId): Customer
    {
        return Customer::create(array_merge($this->customerDefaults('0900000002'), [
            'name' => 'Referred',
            'referred_by_customer_id' => $referrerId,
        ]));
    }

    private function customerDefaults(string $mobile): array
    {
        return [
            'firebase_id' => '',
            'email' => $mobile . '@test.local',
            'logintype' => 'mobile',
            'isActive' => 1,
            'mobile' => $mobile,
            'address' => '',
        ];
    }

    private function makeOrder(int $customerId, int $total): ZaloOrder
    {
        return ZaloOrder::create([
            'customer_id' => $customerId,
            'status' => 'pending',
            'payment_status' => 'success',
            'total' => $total,
            'created_at' => now(),
        ]);
    }
}
