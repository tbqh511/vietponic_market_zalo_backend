<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\ZaloProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin web controller cho Farm Partner Hub.
 *
 * 3 nhóm action:
 *   - requestsIndex / approveRequest / rejectRequest: xử lý yêu cầu đăng ký
 *     (customers.farm_partner_status='requested') — Giai đoạn 1 wireframe.
 *   - index/show/edit/update + suspend/reactivate + destroy: CRUD Farm —
 *     Giai đoạn 2 wireframe.
 *   - attachProduct/detachProduct: cấu hình pivot farm_product (Tab 2 màn
 *     chi tiết Farm).
 *
 * Payout (Giai đoạn 3) tách sang FarmPayoutController vì lifecycle riêng.
 */
class FarmController extends Controller
{
    // ────────────────────────────────────────────────────────────────────
    //  GIAI ĐOẠN 1: YÊU CẦU ĐĂNG KÝ
    // ────────────────────────────────────────────────────────────────────

    /**
     * Danh sách customer đang yêu cầu trở thành Farm Partner.
     * Lọc trực tiếp customers.farm_partner_status='requested' — không có bảng
     * "đơn yêu cầu" riêng vì status chính là source-of-truth.
     */
    public function requestsIndex(Request $request)
    {
        $query = Customer::query()->where('farm_partner_status', 'requested');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('mobile', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('updated_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('updated_at', '<=', $request->date_to);
        }

        $requests = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();

        return view('admin.farms.requests_index', compact('requests'));
    }

    /**
     * Duyệt yêu cầu: gộp 2 bước (đổi role customer + tạo Farm record) trong
     * 1 transaction để tránh state inconsistency nếu DB fail giữa chừng.
     */
    public function approveRequest(Request $request, int $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        if ($customer->farm_partner_status !== 'requested') {
            return back()->with('error', 'Khách hàng này không ở trạng thái "đang yêu cầu".');
        }

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:farms,code',
            'note' => 'nullable|string|max:1000',
        ], [
            'code.unique' => 'Mã Farm đã tồn tại — chọn mã khác.',
        ]);

        DB::transaction(function () use ($customer, $data) {
            $customer->role                = 'farm_partner';
            $customer->farm_partner_status = 'approved';
            $customer->save();

            Farm::create([
                'code'              => $data['code'],
                'name'              => $data['name'],
                'owner_customer_id' => $customer->id,
                // Mặc định khi mới duyệt — admin có thể sửa lại trong tab Thông tin.
                'commission_rate'   => 0.8500,
                'payment_cycle'     => 'monthly',
                'is_active'         => true,
                'approved_at'       => Carbon::now(),
                'approved_by'       => Auth::id(),
                // note đăng ký lưu vào description (tạm); nếu cần audit log riêng
                // sẽ tách bảng farm_logs ở task khác.
                'description'       => $data['note'] ?? null,
            ]);
        });

        $farm = Farm::where('owner_customer_id', $customer->id)->latest('id')->first();

        return redirect()->route('farms.show', $farm->id)
            ->with('success', "Đã duyệt và tạo Farm \"{$farm->name}\" (mã {$farm->code}).");
    }

    /**
     * Từ chối yêu cầu: reset về 'none' + role 'customer'.
     * Không có enum 'rejected' trong DB — đã quyết định không thêm để tiết kiệm
     * migration; khách có thể xin lại sau nếu admin đồng ý.
     */
    public function rejectRequest(int $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        if ($customer->farm_partner_status !== 'requested') {
            return back()->with('error', 'Khách hàng này không ở trạng thái "đang yêu cầu".');
        }

        $customer->farm_partner_status = 'none';
        $customer->role                = 'customer';
        $customer->save();

        return redirect()->route('farm-requests.index')
            ->with('success', "Đã từ chối yêu cầu của {$customer->name}.");
    }

    // ────────────────────────────────────────────────────────────────────
    //  GIAI ĐOẠN 2: QUẢN LÝ FARM
    // ────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Farm::query()->with('owner')->withCount('products');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        // Trạng thái tổng hợp: active = is_active=1 + approved_at; suspended = is_active=0
        if ($request->filled('status')) {
            match ($request->status) {
                'active'    => $query->where('is_active', true)->whereNotNull('approved_at'),
                'suspended' => $query->where('is_active', false),
                default     => null,
            };
        }

        $farms = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.farms.index', compact('farms'));
    }

    public function show(int $id)
    {
        $farm = Farm::with(['owner', 'approver', 'products'])->findOrFail($id);

        // Tab "Kho": chỉ lấy 50 batch gần nhất, không paginate cho gọn.
        $batches = FarmStockBatch::with('product')
            ->where('farm_id', $farm->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Dropdown "Gắn thêm sản phẩm" trong Tab 2: chỉ hiện product chưa gắn.
        $attachedIds = $farm->products->pluck('id')->all();
        $availableProducts = ZaloProduct::query()
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.farms.show', compact('farm', 'batches', 'availableProducts'));
    }

    public function edit(int $id)
    {
        $farm = Farm::with('owner')->findOrFail($id);
        return view('admin.farms.edit', compact('farm'));
    }

    public function update(Request $request, int $id)
    {
        $farm = Farm::findOrFail($id);

        $data = $request->validate([
            'name'            => 'required|string|max:150',
            'description'     => 'nullable|string|max:1000',
            'address'         => 'nullable|string|max:255',
            'commission_rate' => 'required|numeric|min:0|max:1',
            'payment_cycle'   => 'required|in:weekly,biweekly,monthly',
            'is_active'       => 'required|in:0,1',
        ]);

        // KHÔNG cho đổi code (immutable) và KHÔNG cho đổi owner_customer_id
        // qua form thường — tránh nhầm lẫn audit. Nếu cần chuyển chủ farm,
        // làm action riêng "transfer ownership" ở task khác.
        $farm->fill($data)->save();

        return redirect()->route('farms.show', $farm->id)
            ->with('success', 'Đã cập nhật thông tin farm.');
    }

    /**
     * Tạm khoá farm: đồng bộ 2 nơi — farms.is_active=false để frontend không
     * hiển thị, customers.farm_partner_status='suspended' để middleware
     * zalo.farm chặn truy cập Hub. Nếu thiếu 1 trong 2 thì sẽ có khe hở.
     */
    public function suspend(int $id)
    {
        $farm = Farm::with('owner')->findOrFail($id);

        DB::transaction(function () use ($farm) {
            $farm->is_active = false;
            $farm->save();

            if ($farm->owner) {
                $farm->owner->farm_partner_status = 'suspended';
                $farm->owner->save();
            }
        });

        return redirect()->route('farms.show', $farm->id)
            ->with('success', 'Đã tạm khoá Farm và chặn truy cập Hub.');
    }

    public function reactivate(int $id)
    {
        $farm = Farm::with('owner')->findOrFail($id);

        DB::transaction(function () use ($farm) {
            $farm->is_active = true;
            if (!$farm->approved_at) {
                // Trường hợp farm chưa từng duyệt (legacy data) — set lại.
                $farm->approved_at = Carbon::now();
                $farm->approved_by = Auth::id();
            }
            $farm->save();

            if ($farm->owner) {
                $farm->owner->farm_partner_status = 'approved';
                $farm->owner->role                = 'farm_partner';
                $farm->owner->save();
            }
        });

        return redirect()->route('farms.show', $farm->id)
            ->with('success', 'Đã kích hoạt lại Farm.');
    }

    public function destroy(int $id)
    {
        $farm = Farm::findOrFail($id);

        // Chặn xoá khi farm còn dữ liệu vận hành — tránh mất audit trail.
        if ($farm->stockBatches()->exists()) {
            return back()->with('error', 'Farm còn batch tồn kho — không thể xoá. Hãy tạm khoá thay vì xoá.');
        }
        if ($farm->payouts()->exists()) {
            return back()->with('error', 'Farm đã có lịch sử payout — không thể xoá. Hãy tạm khoá thay vì xoá.');
        }
        if ($farm->orderItems()->exists()) {
            return back()->with('error', 'Farm đã có đơn hàng phân bổ — không thể xoá. Hãy tạm khoá thay vì xoá.');
        }

        DB::transaction(function () use ($farm) {
            // Reset customer về trạng thái thường để có thể đăng ký lại.
            if ($farm->owner) {
                $farm->owner->farm_partner_status = 'none';
                $farm->owner->role                = 'customer';
                $farm->owner->save();
            }
            $farm->products()->detach();
            $farm->delete();
        });

        return redirect()->route('farms.index')
            ->with('success', 'Đã xoá Farm.');
    }

    // ────────────────────────────────────────────────────────────────────
    //  TAB "CẤU HÌNH SẢN PHẨM"
    // ────────────────────────────────────────────────────────────────────

    public function attachProduct(Request $request, int $farmId)
    {
        $farm = Farm::findOrFail($farmId);

        $data = $request->validate([
            'product_id' => 'required|integer|exists:zalo_products,id',
            'cost_price' => 'required|numeric|min:0',
            'is_primary' => 'nullable|boolean',
        ]);

        // Tránh duplicate: pivot có unique(farm_id, product_id) — kiểm tra
        // trước để báo lỗi đẹp thay vì để DB ném SQL exception.
        if ($farm->products()->where('product_id', $data['product_id'])->exists()) {
            return back()->with('error', 'Sản phẩm này đã được gắn vào farm.');
        }

        $farm->products()->attach($data['product_id'], [
            'cost_price' => $data['cost_price'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        return redirect()->route('farms.show', $farm->id)
            ->with('success', 'Đã gắn sản phẩm vào farm.');
    }

    public function detachProduct(int $farmId, int $productId)
    {
        $farm = Farm::findOrFail($farmId);

        // Chặn detach nếu farm còn batch active cho product này — nếu detach
        // pivot mà còn batch sẽ làm batch "mồ côi" trong UI cấu hình.
        $hasActiveBatch = FarmStockBatch::query()
            ->where('farm_id', $farm->id)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->exists();

        if ($hasActiveBatch) {
            return back()->with('error', 'Sản phẩm còn batch tồn kho active — đóng batch trước khi gỡ.');
        }

        $farm->products()->detach($productId);

        return redirect()->route('farms.show', $farm->id)
            ->with('success', 'Đã gỡ sản phẩm khỏi farm.');
    }

    // ────────────────────────────────────────────────────────────────────
    //  HELPER (dùng từ view JS để gợi ý code Farm)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Sinh mã farm gợi ý dạng FARM-{SLUG}-001. Dùng làm hint cho admin trong
     * modal duyệt. Không đảm bảo unique — admin phải tự xác nhận hoặc đổi
     * trước khi submit (validate unique tại approveRequest).
     */
    public static function suggestCode(string $name): string
    {
        $slug = Str::slug($name, '');           // bỏ dấu + xoá ký tự đặc biệt
        $slug = strtoupper(substr($slug, 0, 10));
        if ($slug === '') {
            $slug = 'F';
        }
        return 'FARM-' . $slug . '-001';
    }
}
