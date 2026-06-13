<?php

namespace Tests\Feature;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Models\ZaloOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

/**
 * AFF-05: Admin/AffiliatePartnerController
 *   - approve / reject / suspend (updateStatus, destroy)
 *   - payout FIFO (createPayout)
 *   - toggle settings (commission rate, auto-approve, enabled)
 *
 * Auth qua actingAs($admin) — web routes, assert redirect.
 * Code đã đúng, test này chỉ bổ sung coverage.
 */
class AffiliateAdminTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);
        Setting::updateOrCreate(['type' => 'affiliate_auto_approve'], ['data' => '1']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'status'      => 1,
            'type'        => 0,
            'permissions' => '',
        ]);
    }

    private function makePartner(array $overrides = []): Customer
    {
        return $this->makeApprovedAffiliate('ADM' . uniqid(), $overrides);
    }

    private function makeCommission(Customer $partner, int $amount, string $status = 'confirmed', ?string $createdAt = null): AffiliateCommission
    {
        $referred = $this->makeCustomer();
        $order = ZaloOrder::create([
            'customer_id'    => $referred->id,
            'status'         => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'COD',
            'total'          => $amount * 20,
            'created_at'     => $createdAt ?? now(),
        ]);
        return AffiliateCommission::create([
            'referrer_customer_id' => $partner->id,
            'referred_customer_id' => $referred->id,
            'order_id'             => $order->id,
            'order_total'          => $amount * 20,
            'commission_rate'      => 5,
            'commission_amount'    => $amount,
            'status'               => $status,
            'created_at'           => $createdAt ?? now(),
        ]);
    }

    // ── approve / reject ────────────────────────────────────────────────────

    /** PATCH status=approved → set approved_at nếu chưa có */
    public function test_approve_sets_status_and_approved_at(): void
    {
        $partner = $this->makeCustomer([
            'affiliate_code'       => 'PENDING1',
            'affiliate_status'     => 'pending',
            'affiliate_approved_at' => null,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('affiliate-partners.status', $partner->id), [
                'affiliate_status' => 'approved',
            ])
            ->assertRedirect();

        $partner->refresh();
        $this->assertSame('approved', $partner->affiliate_status);
        $this->assertNotNull($partner->affiliate_approved_at);
    }

    /** approve khi đã có approved_at → không ghi đè */
    public function test_approve_does_not_overwrite_existing_approved_at(): void
    {
        $originalDate = now()->subDays(5);
        $partner = $this->makePartner([
            'affiliate_approved_at' => $originalDate,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('affiliate-partners.status', $partner->id), [
                'affiliate_status' => 'approved',
            ])
            ->assertRedirect();

        $partner->refresh();
        $this->assertEquals(
            $originalDate->toDateString(),
            $partner->affiliate_approved_at->toDateString()
        );
    }

    /** DELETE → status='rejected', redirect về index */
    public function test_reject_sets_status_rejected(): void
    {
        $partner = $this->makePartner();

        $this->actingAs($this->admin())
            ->delete(route('affiliate-partners.destroy', $partner->id))
            ->assertRedirect(route('affiliate-partners.index'));

        $partner->refresh();
        $this->assertSame('rejected', $partner->affiliate_status);
    }

    /** PATCH status=suspended → DB cập nhật, không set approved_at */
    public function test_suspend_via_updateStatus(): void
    {
        $partner = $this->makePartner(['affiliate_approved_at' => null]);

        $this->actingAs($this->admin())
            ->patch(route('affiliate-partners.status', $partner->id), [
                'affiliate_status' => 'suspended',
            ])
            ->assertRedirect();

        $partner->refresh();
        $this->assertSame('suspended', $partner->affiliate_status);
        $this->assertNull($partner->affiliate_approved_at);
    }

    // ── payout FIFO ─────────────────────────────────────────────────────────

    /**
     * 3 commission confirmed: 1000 (cũ nhất), 2000, 3000.
     * Payout amount=3000 → mark 1000 + 2000 = paid; 3000 vẫn confirmed.
     */
    public function test_payout_marks_commissions_fifo(): void
    {
        $partner = $this->makePartner();

        $c1 = $this->makeCommission($partner, 1000, 'confirmed', now()->subHours(3)->toDateTimeString());
        $c2 = $this->makeCommission($partner, 2000, 'confirmed', now()->subHours(2)->toDateTimeString());
        $c3 = $this->makeCommission($partner, 3000, 'confirmed', now()->subHours(1)->toDateTimeString());

        $this->actingAs($this->admin())
            ->post(route('affiliate-partners.payouts.create', $partner->id), [
                'amount' => 3000,
            ])
            ->assertRedirect();

        $this->assertSame('paid', $c1->fresh()->status);
        $this->assertSame('paid', $c2->fresh()->status);
        $this->assertSame('confirmed', $c3->fresh()->status);
    }

    /**
     * 1 commission confirmed amount=5000, payout amount=3000.
     * Commission không bị chia nhỏ → vẫn confirmed.
     */
    public function test_payout_skips_commission_larger_than_remaining(): void
    {
        $partner = $this->makePartner();
        $c = $this->makeCommission($partner, 5000);

        $this->actingAs($this->admin())
            ->post(route('affiliate-partners.payouts.create', $partner->id), [
                'amount' => 3000,
            ])
            ->assertRedirect();

        $this->assertSame('confirmed', $c->fresh()->status);
    }

    /** POST payout → có 1 row affiliate_payouts, status=paid, amount đúng, method=bank_transfer */
    public function test_payout_creates_payout_record(): void
    {
        $partner = $this->makePartner();
        $this->makeCommission($partner, 10000);

        $this->actingAs($this->admin())
            ->post(route('affiliate-partners.payouts.create', $partner->id), [
                'amount'    => 10000,
                'reference' => 'REF-001',
                'notes'     => 'Chi tháng 6',
            ])
            ->assertRedirect();

        $payout = AffiliatePayout::where('referrer_customer_id', $partner->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame('paid', $payout->status);
        $this->assertSame(10000, (int) $payout->amount);
        $this->assertSame('bank_transfer', $payout->method);
        $this->assertSame('REF-001', $payout->reference);
    }

    // ── toggle settings ──────────────────────────────────────────────────────

    /** PATCH commission-rate → Setting cập nhật */
    public function test_toggle_commission_rate(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('affiliate-settings.commission-rate'), ['rate' => 7.5])
            ->assertRedirect();

        $this->assertSame('7.5', Setting::where('type', 'affiliate_commission_rate')->value('data'));
    }

    /** PATCH auto-approve enabled=0 → Setting='0' */
    public function test_toggle_auto_approve_off(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('affiliate-settings.auto-approve'), ['enabled' => 0])
            ->assertRedirect();

        $this->assertSame('0', Setting::where('type', 'affiliate_auto_approve')->value('data'));
    }

    /** PATCH enabled=0 → Setting='0' */
    public function test_toggle_enabled_off(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('affiliate-settings.enabled'), ['enabled' => 0])
            ->assertRedirect();

        $this->assertSame('0', Setting::where('type', 'affiliate_enabled')->value('data'));
    }
}
