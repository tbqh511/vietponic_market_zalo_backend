<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin web controller cho danh sách khách hàng Zalo. Trước đây có chức năng
 * toggle Farm Partner qua bảng farm_partners — đã chuyển sang cột
 * Customer.role / Customer.farm_partner_status + model Farm. Toggle UI tạm gỡ,
 * sẽ làm lại trong admin Farm Hub task riêng.
 */
class ZaloCustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

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

        // Filter theo trạng thái Farm Partner đọc trực tiếp từ cột customers.
        // 'approved'  — đã duyệt làm farm partner (truy cập được Hub).
        // 'requested' — đang xin duyệt.
        // 'none'      — chưa bao giờ xin / đã bị huỷ.
        if ($request->filled('farm_status')) {
            match ($request->farm_status) {
                'approved'  => $query->where('role', 'farm_partner')->where('farm_partner_status', 'approved'),
                'requested' => $query->where('farm_partner_status', 'requested'),
                'none'      => $query->where(function ($w) {
                    $w->whereNull('farm_partner_status')
                      ->orWhere('farm_partner_status', 'none');
                }),
                default     => null,
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
        $customer = Customer::with(['referrer', 'farm'])->findOrFail($id);

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

    public function toggleActive(int $id)
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
