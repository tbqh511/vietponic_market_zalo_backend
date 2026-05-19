<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $query = Customer::query()->with('farm');

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

        // Farms chưa có owner — hiển thị trong modal "Chỉ định Farm Partner"
        // để admin chọn gán customer vào farm có sẵn thay vì luôn tạo mới.
        $availableFarms = Farm::query()
            ->whereNull('owner_customer_id')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('admin.zalo_customers.index', compact('customers', 'availableFarms'));
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

    /**
     * Chỉ định khách hàng làm Farm Partner ngay từ trang list.
     * Xử lý cả 3 trạng thái nguồn: none / requested / suspended.
     * 2 mode:
     *   - existing: gán vào farm có sẵn (chưa có owner)
     *   - new:      tạo farm mới với name + address + description
     * Toàn bộ thay đổi gói trong DB::transaction để tránh state nửa vời nếu fail.
     */
    public function promoteFarmPartner(Request $request, int $id)
    {
        $rules = [
            'mode' => 'required|in:existing,new',
        ];
        if ($request->input('mode') === 'existing') {
            $rules['farm_id'] = 'required|integer|exists:farms,id';
        } else {
            $rules['farm_name']        = 'required|string|max:150';
            $rules['farm_address']     = 'nullable|string|max:255';
            $rules['farm_description'] = 'nullable|string|max:1000';
        }
        $data = $request->validate($rules);

        $customer = Customer::with('farm')->findOrFail($id);

        if (!$customer->isActive) {
            return back()->with('error', 'Tài khoản đang bị vô hiệu hoá — kích hoạt trước khi chỉ định Farm Partner.');
        }

        // Đã là farm partner approved + có farm rồi → từ chối duplicate.
        if ($customer->role === 'farm_partner'
            && $customer->farm_partner_status === 'approved'
            && $customer->farm) {
            return back()->with('error', "Khách hàng đã là Farm Partner của farm \"{$customer->farm->name}\".");
        }

        try {
            $farm = DB::transaction(function () use ($customer, $data) {
                if ($data['mode'] === 'existing') {
                    // Lock row để tránh 2 admin gán cùng farm cho 2 customer.
                    $farm = Farm::lockForUpdate()->findOrFail($data['farm_id']);
                    if (!is_null($farm->owner_customer_id) && $farm->owner_customer_id !== $customer->id) {
                        throw new \DomainException("Farm \"{$farm->name}\" đã có chủ — chọn farm khác hoặc tạo mới.");
                    }
                    $farm->owner_customer_id = $customer->id;
                    $farm->is_active         = true;
                    if (!$farm->approved_at) {
                        $farm->approved_at = Carbon::now();
                        $farm->approved_by = Auth::id();
                    }
                    $farm->save();
                } else {
                    // Sinh code unique từ tên — retry với suffix nếu trùng.
                    $code = $this->generateUniqueFarmCode($data['farm_name']);
                    $farm = Farm::create([
                        'code'              => $code,
                        'name'              => $data['farm_name'],
                        'owner_customer_id' => $customer->id,
                        'address'           => $data['farm_address']     ?? null,
                        'description'       => $data['farm_description'] ?? null,
                        'commission_rate'   => 0.8500,
                        'payment_cycle'     => 'monthly',
                        'is_active'         => true,
                        'approved_at'       => Carbon::now(),
                        'approved_by'       => Auth::id(),
                    ]);
                }

                $customer->role                = 'farm_partner';
                $customer->farm_partner_status = 'approved';
                $customer->save();

                return $farm;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with(
            'success',
            "Đã chỉ định {$customer->name} làm Farm Partner — Farm: {$farm->name} ({$farm->code})."
        );
    }

    /**
     * Tạm dừng vai trò Farm Partner trực tiếp từ row.
     * Đồng bộ 2 nơi: customers.farm_partner_status='suspended' + farms.is_active=false.
     * Giữ role='farm_partner' để có thể reactivate từ trang /farms.
     */
    public function suspendFarmPartner(int $id)
    {
        $customer = Customer::with('farm')->findOrFail($id);

        if ($customer->farm_partner_status === 'suspended') {
            return back()->with('error', 'Vai trò Farm Partner đã ở trạng thái tạm dừng.');
        }
        if ($customer->role !== 'farm_partner' || $customer->farm_partner_status !== 'approved') {
            return back()->with('error', 'Khách hàng này chưa phải Farm Partner đang hoạt động.');
        }

        DB::transaction(function () use ($customer) {
            $customer->farm_partner_status = 'suspended';
            $customer->save();

            if ($customer->farm) {
                $customer->farm->is_active = false;
                $customer->farm->save();
            }
        });

        return back()->with('success', "Đã tạm dừng vai trò Farm Partner của {$customer->name}.");
    }

    /**
     * Sinh mã farm unique từ tên. Bắt đầu từ FARM-{SLUG}-001; nếu trùng,
     * gắn thêm suffix random 4 ký tự. Tối đa 5 lần thử trước khi throw.
     */
    private function generateUniqueFarmCode(string $name): string
    {
        $base = \App\Http\Controllers\Admin\FarmController::suggestCode($name);
        if (!Farm::where('code', $base)->exists()) {
            return $base;
        }
        for ($i = 0; $i < 5; $i++) {
            $candidate = $base . '-' . strtoupper(Str::random(4));
            if (!Farm::where('code', $candidate)->exists()) {
                return $candidate;
            }
        }
        throw new \RuntimeException('Không sinh được mã Farm unique sau 5 lần thử — vui lòng đổi tên Farm và thử lại.');
    }
}
