<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FarmPartner;
use App\Models\FarmPartnerLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZaloCustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->with('farmPartner');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('mobile', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('isActive', $request->is_active);
        }

        if ($request->filled('farm_status')) {
            match ($request->farm_status) {
                'active'   => $query->whereHas('farmPartner', fn ($q) => $q->where('status', 'active')),
                'inactive' => $query->whereHas('farmPartner', fn ($q) => $q->where('status', 'inactive')),
                'none'     => $query->doesntHave('farmPartner'),
                default    => null,
            };
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $customers = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.zalo_customers.index', compact('customers'));
    }

    public function show(int $id)
    {
        $customer = Customer::with(['farmPartner', 'referrer', 'farmPartnerLogs.admin'])
            ->findOrFail($id);

        $ordersCount    = $customer->orders()->count();
        $ordersTotal    = $customer->orders()->sum(DB::raw('CAST(total AS DECIMAL(20,2))'));
        $referralsCount = $customer->referrals()->count();
        $recentOrders   = $customer->orders()->orderByDesc('created_at')->limit(5)->get();

        return view('admin.zalo_customers.show', compact(
            'customer',
            'ordersCount',
            'ordersTotal',
            'referralsCount',
            'recentOrders'
        ));
    }

    public function edit(int $id)
    {
        $customer = Customer::findOrFail($id);

        return view('admin.zalo_customers.edit', compact('customer'));
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);

        $data = $request->validate([
            'name'     => 'nullable|string|max:120',
            'email'    => 'nullable|email|max:120|unique:customers,email,' . $id,
            'mobile'   => 'nullable|string|max:30|unique:customers,mobile,' . $id,
            'address'  => 'nullable|string|max:500',
            'isActive' => 'required|in:0,1',
        ]);

        $customer->fill($data)->save();

        return redirect()->route('zalo-customers.show', $customer->id)
            ->with('success', 'Đã cập nhật thông tin khách hàng.');
    }

    public function destroy(int $id)
    {
        $customer    = Customer::findOrFail($id);
        $ordersCount = $customer->orders()->count();

        if ($ordersCount > 0) {
            return redirect()->route('zalo-customers.index')
                ->with('error', "Không thể xoá: khách hàng #{$id} có {$ordersCount} đơn hàng. Hãy vô hiệu hoá tài khoản thay vì xoá.");
        }

        $customer->delete();

        return redirect()->route('zalo-customers.index')
            ->with('success', 'Đã xoá khách hàng.');
    }

    public function toggleFarmPartner(Request $request, int $id)
    {
        $customer = Customer::with('farmPartner')->findOrFail($id);

        $data = $request->validate([
            'change_reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($customer, $data) {
            $farmPartner = $customer->farmPartner;
            $oldStatus   = $farmPartner?->status;

            if (is_null($farmPartner)) {
                $farmPartner = FarmPartner::create([
                    'customer_id' => $customer->id,
                    'status'      => 'active',
                    'farm_name'   => $customer->name . "'s Farm",
                    'note'        => null,
                ]);
                $action    = 'created';
                $newStatus = 'active';
            } else {
                $newStatus = $farmPartner->status === 'active' ? 'inactive' : 'active';
                $farmPartner->update(['status' => $newStatus]);
                $action = $newStatus === 'active' ? 'activated' : 'deactivated';
            }

            FarmPartnerLog::create([
                'customer_id'     => $customer->id,
                'farm_partner_id' => $farmPartner->id,
                'action'          => $action,
                'old_status'      => $oldStatus,
                'new_status'      => $newStatus,
                'changed_by'      => auth()->id(),
                'change_reason'   => $data['change_reason'] ?? null,
            ]);
        });

        $customer->refresh();
        $newStatus = $customer->farmPartner?->status;
        $msg = $newStatus === 'active'
            ? 'Đã kích hoạt trạng thái Farm Partner.'
            : 'Đã tắt trạng thái Farm Partner.';

        return redirect()->route('zalo-customers.show', $customer->id)
            ->with('success', $msg);
    }

    public function toggleActive(Request $request, int $id)
    {
        $customer           = Customer::findOrFail($id);
        $customer->isActive = $customer->isActive ? 0 : 1;
        $customer->save();

        $msg = $customer->isActive
            ? 'Đã kích hoạt tài khoản khách hàng.'
            : 'Đã vô hiệu hoá tài khoản khách hàng.';

        return redirect()->back()->with('success', $msg);
    }
}
