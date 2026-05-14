<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AffiliatePartnerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->whereNotNull('affiliate_code');
        if ($request->filled('status')) {
            $query->where('affiliate_status', $request->status);
        }
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($w) use ($q) {
                $w->where('affiliate_code', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        $partners = $query->orderByDesc('affiliate_approved_at')->orderByDesc('id')->paginate(20);

        $commissionRate = Setting::where('type', 'affiliate_commission_rate')->value('data') ?? '5';
        $autoApprove = (string) (Setting::where('type', 'affiliate_auto_approve')->value('data') ?? '1') === '1';
        $enabled = (string) (Setting::where('type', 'affiliate_enabled')->value('data') ?? '1') === '1';

        return view('admin.affiliate_partners.index', compact('partners', 'commissionRate', 'autoApprove', 'enabled'));
    }

    public function show($id)
    {
        $partner = Customer::whereNotNull('affiliate_code')->findOrFail($id);
        $stats = AffiliateCommission::where('referrer_customer_id', $partner->id)
            ->selectRaw('status, COALESCE(SUM(commission_amount),0) as total, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        $commissions = AffiliateCommission::where('referrer_customer_id', $partner->id)
            ->orderByDesc('created_at')->paginate(20, ['*'], 'commissions_page');
        $referralsCount = Customer::where('referred_by_customer_id', $partner->id)->count();
        $payouts = AffiliatePayout::where('referrer_customer_id', $partner->id)
            ->orderByDesc('created_at')->limit(10)->get();

        $ordersAgg = DB::table('zalo_orders')
            ->selectRaw('customer_id, COUNT(*) as orders_count, COALESCE(SUM(CAST(total AS DECIMAL(20,2))),0) as orders_total')
            ->groupBy('customer_id');
        $commissionAgg = DB::table('affiliate_commissions')
            ->where('referrer_customer_id', $partner->id)
            ->selectRaw('referred_customer_id, COALESCE(SUM(commission_amount),0) as commission_total')
            ->groupBy('referred_customer_id');

        $referrals = Customer::where('customers.referred_by_customer_id', $partner->id)
            ->leftJoinSub($ordersAgg, 'o', function ($j) {
                $j->on('o.customer_id', '=', 'customers.id');
            })
            ->leftJoinSub($commissionAgg, 'c', function ($j) {
                $j->on('c.referred_customer_id', '=', 'customers.id');
            })
            ->select(
                'customers.id',
                'customers.name',
                'customers.mobile',
                'customers.email',
                'customers.created_at',
                DB::raw('COALESCE(o.orders_count, 0) as orders_count'),
                DB::raw('COALESCE(o.orders_total, 0) as orders_total'),
                DB::raw('COALESCE(c.commission_total, 0) as commission_total')
            )
            ->orderByDesc('customers.id')
            ->paginate(20, ['*'], 'referrals_page');

        return view('admin.affiliate_partners.show', compact('partner', 'stats', 'commissions', 'referralsCount', 'payouts', 'referrals'));
    }

    public function edit($id)
    {
        $partner = Customer::whereNotNull('affiliate_code')->findOrFail($id);
        return view('admin.affiliate_partners.edit', compact('partner'));
    }

    public function update(Request $request, $id)
    {
        $partner = Customer::whereNotNull('affiliate_code')->findOrFail($id);
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'mobile' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:120',
            'affiliate_bank_name' => 'nullable|string|max:120',
            'affiliate_bank_account' => 'nullable|string|max:60',
            'affiliate_bank_holder' => 'nullable|string|max:120',
        ]);
        $partner->fill($data)->save();
        return redirect()->route('affiliate-partners.show', $partner->id)
            ->with('success', 'Đã cập nhật thông tin cộng tác viên');
    }

    public function updateStatus(Request $request, $id)
    {
        $partner = Customer::whereNotNull('affiliate_code')->findOrFail($id);
        $data = $request->validate([
            'affiliate_status' => 'required|in:pending,approved,suspended,rejected',
        ]);
        $partner->affiliate_status = $data['affiliate_status'];
        if ($data['affiliate_status'] === 'approved' && !$partner->affiliate_approved_at) {
            $partner->affiliate_approved_at = Carbon::now();
        }
        $partner->save();
        return redirect()->route('affiliate-partners.show', $partner->id)
            ->with('success', 'Đã đổi trạng thái cộng tác viên');
    }

    public function destroy($id)
    {
        $partner = Customer::whereNotNull('affiliate_code')->findOrFail($id);
        $partner->affiliate_status = 'rejected';
        $partner->save();
        return redirect()->route('affiliate-partners.index')
            ->with('success', 'Đã từ chối cộng tác viên');
    }

    public function updateCommissionRate(Request $request)
    {
        $data = $request->validate([
            'rate' => 'required|numeric|min:0|max:100',
        ]);
        Setting::updateOrCreate(['type' => 'affiliate_commission_rate'], ['data' => (string) $data['rate']]);
        return back()->with('success', 'Đã cập nhật tỷ lệ hoa hồng: ' . $data['rate'] . '%');
    }

    public function toggleAutoApprove(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        Setting::updateOrCreate(['type' => 'affiliate_auto_approve'], ['data' => $data['enabled'] ? '1' : '0']);
        return back()->with('success', 'Đã cập nhật chế độ duyệt tự động');
    }

    public function toggleEnabled(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        Setting::updateOrCreate(['type' => 'affiliate_enabled'], ['data' => $data['enabled'] ? '1' : '0']);
        return back()->with('success', 'Đã cập nhật trạng thái module affiliate');
    }

    public function createPayout(Request $request, $partnerId)
    {
        $partner = Customer::whereNotNull('affiliate_code')->findOrFail($partnerId);
        $data = $request->validate([
            'amount' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($partner, $data) {
            $payout = AffiliatePayout::create([
                'referrer_customer_id' => $partner->id,
                'amount' => $data['amount'],
                'status' => 'paid',
                'method' => 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'requested_at' => Carbon::now(),
                'paid_at' => Carbon::now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Mark up to $amount of confirmed commissions as paid (FIFO)
            $remaining = (int) $data['amount'];
            $commissions = AffiliateCommission::where('referrer_customer_id', $partner->id)
                ->where('status', 'confirmed')
                ->orderBy('created_at')
                ->get();
            foreach ($commissions as $c) {
                if ($remaining <= 0) break;
                if ($c->commission_amount <= $remaining) {
                    $c->status = 'paid';
                    $c->paid_at = Carbon::now();
                    $c->save();
                    $remaining -= $c->commission_amount;
                }
            }
        });

        return redirect()->route('affiliate-partners.show', $partner->id)
            ->with('success', 'Đã ghi nhận chi trả');
    }
}
