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

        $farm = DB::transaction(function () use ($customer, $data) {
            $farm = Farm::create([
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

            // Sync farm_id/farm_role để middleware EnsureFarmPartner (đã đổi
            // sang lookup theo farm_id) cấp quyền đúng. Owner ↔ farm 1:1.
            $customer->role                = 'farm_partner';
            $customer->farm_partner_status = 'approved';
            $customer->farm_id             = $farm->id;
            $customer->farm_role           = 'owner';
            $customer->save();

            return $farm;
        });

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

    public function show(Request $request, int $id)
    {
        $farm = Farm::with(['owner', 'approver', 'products', 'staff'])->findOrFail($id);

        // Tab "Kho": chỉ lấy 50 batch gần nhất, không paginate cho gọn.
        $batches = FarmStockBatch::with('product')
            ->where('farm_id', $farm->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Tab "Cấu hình Sản phẩm": paginated + search + filter.
        $farmProducts = $farm->products()
            ->when($request->filled('product_q'), function ($q) use ($request) {
                $term = trim($request->product_q);
                $q->where(function ($w) use ($term) {
                    $w->where('zalo_products.name', 'like', "%{$term}%")
                      ->orWhere('zalo_products.id', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('product_primary') && $request->product_primary !== '', function ($q) use ($request) {
                $q->wherePivot('is_primary', (bool) $request->product_primary);
            })
            ->orderBy('zalo_products.name')
            ->paginate(15, ['zalo_products.*'], 'product_page')
            ->withQueryString();

        // Dropdown "Gắn thêm sản phẩm" trong Tab 2: chỉ hiện product chưa gắn.
        $attachedIds = $farm->products->pluck('id')->all();
        $availableProducts = ZaloProduct::query()
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Chỉ cần check tồn tại — danh sách thực được load qua AJAX Select2.
        $hasStaffCandidates = Customer::query()
            ->whereNull('farm_id')
            ->where('isActive', 1)
            ->exists();

        // Ứng viên cho "Chuyển chủ farm": tất cả active customer (trừ chủ hiện
        // tại). Sort: staff của farm này lên đầu (case phổ biến: promote staff
        // hiện có lên owner). Cap 200 — Select2 search-as-you-type xử lý phần
        // còn lại; admin cần ngoài top 200 thì hiếm gặp.
        $ownerId = $farm->owner_customer_id;
        $transferCandidates = Customer::query()
            ->where('isActive', 1)
            ->when($ownerId, fn ($q) => $q->where('id', '!=', $ownerId))
            ->orderByRaw('CASE WHEN farm_id = ? THEN 0 ELSE 1 END', [$farm->id])
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'mobile', 'farm_id', 'farm_role', 'isActive']);

        // Pre-compute warning label (1 query Farm cho toàn bộ — tránh N+1).
        $otherFarmIds = $transferCandidates->pluck('farm_id')
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $farm->id)
            ->values();
        $otherFarmNames = $otherFarmIds->isEmpty()
            ? collect()
            : Farm::whereIn('id', $otherFarmIds)->pluck('name', 'id');

        $transferCandidates->each(function ($c) use ($farm, $otherFarmNames) {
            $c->_transfer_warning      = null;
            $c->_transfer_needs_confirm = false; // chỉ chủ farm khác mới buộc tick
            if ($c->farm_id && $c->farm_id !== $farm->id) {
                $name = $otherFarmNames[$c->farm_id] ?? '#' . $c->farm_id;
                if ($c->farm_role === 'owner') {
                    // Chuyển được, nhưng cần confirm — farm kia sẽ thành mồ côi.
                    $c->_transfer_warning       = "⚠ Đang là CHỦ farm \"{$name}\" — chuyển sẽ làm farm đó mất chủ";
                    $c->_transfer_needs_confirm = true;
                } else {
                    $roleLabel = ['admin' => 'quản lý', 'packer' => 'nhân viên đóng gói', 'shipper' => 'nhân viên giao hàng'][$c->farm_role] ?? 'thành viên';
                    $c->_transfer_warning = "ℹ Đang là {$roleLabel} farm \"{$name}\" — sẽ tự rời farm cũ";
                }
            }
        });

        return view('admin.farms.show', compact(
            'farm',
            'batches',
            'farmProducts',
            'availableProducts',
            'hasStaffCandidates',
            'transferCandidates'
        ));
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

    /**
     * Bật/tắt cờ "bộ phận đóng gói" (Package Hub) cho farm. Chỉ farm là hub mới
     * được vào khâu xử lý/đóng gói đơn trên Mini App. Cho phép nhiều hub — đơn
     * vị đóng gói chọn hub id nhỏ nhất (Farm::primaryPackingHub()).
     *
     * Lưu ý: phiếu đóng gói của đơn đang xử lý chỉ được sinh lại theo hub khi
     * load incoming (FarmHubController::ensureAssignmentsExist) — bật hub mới
     * sẽ tự gắn phiếu cho đơn mới mà không cần thao tác thêm.
     */
    public function togglePackingHub(Farm $farm)
    {
        $farm->is_packing_hub = ! $farm->is_packing_hub;
        $farm->save();

        $msg = $farm->is_packing_hub
            ? "Đã đặt \"{$farm->name}\" làm bộ phận đóng gói (Package Hub)."
            : "Đã gỡ \"{$farm->name}\" khỏi bộ phận đóng gói.";

        return redirect()->back()->with('success', $msg);
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
            // Reset owner về customer thường để có thể đăng ký lại.
            if ($farm->owner) {
                $farm->owner->farm_partner_status = 'none';
                $farm->owner->role                = 'customer';
                $farm->owner->farm_id             = null;
                $farm->owner->farm_role           = null;
                $farm->owner->save();
            }
            // Reset toàn bộ staff thuộc farm — sau khi farm bị xoá thì họ không
            // còn lý do giữ role farm_partner. FK nullOnDelete cũng tự dọn
            // farm_id nhưng farm_role và role/status thì cần code xử lý.
            Customer::where('farm_id', $farm->id)
                ->where('farm_role', 'staff')
                ->update([
                    'farm_id'             => null,
                    'farm_role'           => null,
                    'role'                => 'customer',
                    'farm_partner_status' => 'none',
                ]);

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

        return redirect()->route('farms.show', ['farm' => $farm->id, 'tab' => 'products'])
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

        return redirect()->route('farms.show', ['farm' => $farm->id, 'tab' => 'products'])
            ->with('success', 'Đã gỡ sản phẩm khỏi farm.');
    }

    public function updateProduct(Request $request, int $farmId, int $productId)
    {
        $farm = Farm::findOrFail($farmId);

        $data = $request->validate([
            'cost_price' => 'required|numeric|min:0',
            'is_primary' => 'nullable|boolean',
        ]);

        if (! $farm->products()->where('zalo_products.id', $productId)->exists()) {
            return back()->with('error', 'Sản phẩm không thuộc farm này.');
        }

        $farm->products()->updateExistingPivot($productId, [
            'cost_price' => $data['cost_price'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        return redirect()->route('farms.show', ['farm' => $farmId, 'tab' => 'products'])
            ->with('success', 'Đã cập nhật thông tin sản phẩm.');
    }

    // ────────────────────────────────────────────────────────────────────
    //  TAB "NHÂN VIÊN" — Staff/Operator của farm
    // ────────────────────────────────────────────────────────────────────

    /** AJAX endpoint cho Select2: tìm customer active chưa thuộc farm nào. */
    public function staffCandidates(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->input('q', ''));
        $query = Customer::query()->whereNull('farm_id')->where('isActive', 1);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        $results = $query->orderBy('name')->limit(30)->get(['id', 'name', 'mobile'])
            ->map(fn ($c) => [
                'id'   => $c->id,
                'text' => ($c->name ?: '#'.$c->id).($c->mobile ? ' — '.$c->mobile : ''),
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Gán 1 customer làm staff của farm — full quyền Hub nhưng không nhận
     * payout. Yêu cầu farm đã có owner (farm chưa có chủ thì gán chủ trước
     * cho rõ flow; tránh tình huống farm có staff mà không có chủ).
     *
     * Race condition: 2 admin cùng gán 1 customer cho 2 farm → lockForUpdate
     * trên customer + check farm_id IS NULL.
     */
    public function attachStaff(Request $request, int $farmId)
    {
        $farm = Farm::findOrFail($farmId);

        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'role'        => 'required|in:admin,packer,shipper',
        ]);

        if (is_null($farm->owner_customer_id)) {
            return back()->with('error', 'Farm chưa có chủ — gán chủ trước khi thêm thành viên.');
        }

        try {
            DB::transaction(function () use ($farm, $data) {
                $customer = Customer::lockForUpdate()->findOrFail($data['customer_id']);

                if (!$customer->isActive) {
                    throw new \DomainException('Khách hàng này đang bị vô hiệu hoá.');
                }
                if (!is_null($customer->farm_id)) {
                    $existingFarmName = Farm::where('id', $customer->farm_id)->value('name') ?? '#' . $customer->farm_id;
                    throw new \DomainException("Khách hàng đã thuộc farm \"{$existingFarmName}\" — gỡ khỏi farm cũ trước khi gán farm mới.");
                }
                if ($customer->id === $farm->owner_customer_id) {
                    throw new \DomainException('Customer này đã là chủ farm — không thể đồng thời là thành viên.');
                }

                $customer->farm_id             = $farm->id;
                $customer->farm_role           = $data['role'];
                $customer->role                = 'farm_partner';
                $customer->farm_partner_status = 'approved';
                $customer->save();
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $roleLabel = ['admin' => 'quản lý', 'packer' => 'nhân viên đóng gói', 'shipper' => 'nhân viên giao hàng'][$data['role']] ?? $data['role'];

        return redirect()->route('farms.show', $farm->id)
            ->with('success', "Đã thêm {$roleLabel} vào farm.");
    }

    /**
     * Gỡ staff khỏi farm — reset về customer thường (không giữ history role).
     * Không cho gỡ owner qua route này — owner phải dùng suspend/destroy farm
     * để tránh nhầm lẫn.
     */
    public function detachStaff(int $farmId, int $customerId)
    {
        $farm     = Farm::findOrFail($farmId);
        $customer = Customer::findOrFail($customerId);

        if ($customer->farm_id !== $farm->id) {
            return back()->with('error', 'Nhân viên này không thuộc farm hiện tại.');
        }
        if ($customer->isFarmOwner()) {
            return back()->with('error', 'Không thể gỡ chủ farm qua chức năng này — dùng Tạm khoá hoặc Xoá farm.');
        }

        DB::transaction(function () use ($customer) {
            $customer->farm_id             = null;
            $customer->farm_role           = null;
            $customer->role                = 'customer';
            $customer->farm_partner_status = 'none';
            $customer->save();
        });

        return redirect()->route('farms.show', $farm->id)
            ->with('success', "Đã gỡ {$customer->name} khỏi farm.");
    }

    /**
     * Đổi vai trò của thành viên hiện tại (admin / packer / shipper).
     * Không thể đổi vai trò của owner — dùng transfer-ownership.
     */
    public function changeRole(Request $request, int $farmId, int $customerId)
    {
        $farm     = Farm::findOrFail($farmId);
        $customer = Customer::findOrFail($customerId);

        $data = $request->validate([
            'role' => 'required|in:admin,packer,shipper',
        ]);

        if ($customer->farm_id !== $farm->id) {
            return back()->with('error', 'Thành viên này không thuộc farm hiện tại.');
        }
        if ($customer->isFarmOwner()) {
            return back()->with('error', 'Không thể đổi vai trò của chủ farm — dùng Chuyển chủ farm.');
        }

        $customer->farm_role = $data['role'];
        $customer->save();

        $roleLabel = ['admin' => 'quản lý', 'packer' => 'nhân viên đóng gói', 'shipper' => 'nhân viên giao hàng'][$data['role']];

        return redirect()->route('farms.show', $farm->id)
            ->with('success', "Đã đổi vai trò của {$customer->name} thành {$roleLabel}.");
    }

    // ────────────────────────────────────────────────────────────────────
    //  CHUYỂN CHỦ FARM — Đồng bộ farms.owner_customer_id + customers.farm_*
    // ────────────────────────────────────────────────────────────────────

    /**
     * Chuyển chủ farm cho customer khác. Chủ cũ tự động trở thành Staff cùng
     * farm (giữ quyền Hub, mất payout). Yêu cầu farm đang active.
     *
     * Chủ mới đang gắn với farm khác:
     *   - Nếu là NHÂN VIÊN farm khác → chuyển ngay, tự rời farm cũ (không cần
     *     confirm — rời nhân viên không làm farm kia mồ côi).
     *   - Nếu là CHỦ farm khác → cần confirm_warnings=1; khi qua, farm cũ của họ
     *     bị gỡ chủ (owner_customer_id = null, thành "mồ côi") cho tới khi admin
     *     gán chủ mới. Đây là lựa chọn có chủ ý — admin phải tick xác nhận.
     *
     * Optimistic concurrency: form gửi kèm current_owner_id; nếu DB đã đổi
     * giữa lúc page load và submit → fail sớm thay vì ghi đè im lặng.
     */
    public function transferOwnership(Request $request, int $farmId)
    {
        $farm = Farm::with('owner')->findOrFail($farmId);

        $data = $request->validate([
            'new_owner_id'     => 'required|integer|exists:customers,id',
            'current_owner_id' => 'nullable|integer',
            'confirm_warnings' => 'nullable|boolean',
        ]);

        if (! $farm->is_active) {
            return back()->with('error', 'Farm đang tạm khoá — kích hoạt lại trước khi chuyển chủ.');
        }
        if ((int) $data['new_owner_id'] === (int) ($farm->owner_customer_id ?? 0)) {
            return back()->with('error', 'Customer được chọn đang là chủ farm hiện tại — không có gì để chuyển.');
        }

        // Farm chưa có chủ → đây là thao tác GÁN chủ lần đầu (không có chủ cũ
        // để hạ xuống staff). Dùng cờ này để chọn message phản hồi phù hợp.
        $wasOwnerless = is_null($farm->owner_customer_id);

        try {
            DB::transaction(function () use ($farm, $data) {
                // Lock farm trước, sau đó lock customers theo id tăng dần →
                // tránh deadlock khi 2 transaction cùng đụng cặp customer/farm.
                $farm = Farm::lockForUpdate()->findOrFail($farm->id);

                if ((int) ($data['current_owner_id'] ?? 0) !== (int) ($farm->owner_customer_id ?? 0)) {
                    throw new \DomainException('Chủ farm đã bị thay đổi bởi admin khác — tải lại trang và thử lại.');
                }

                $ids = array_filter([$farm->owner_customer_id, (int) $data['new_owner_id']]);
                sort($ids);
                $locked = Customer::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

                $newOwner = $locked[$data['new_owner_id']] ?? null;
                $oldOwner = $farm->owner_customer_id ? ($locked[$farm->owner_customer_id] ?? null) : null;

                if (! $newOwner) {
                    throw new \DomainException('Không tìm thấy customer mới.');
                }
                if (! $newOwner->isActive) {
                    throw new \DomainException('Customer mới đang bị vô hiệu hoá.');
                }

                // Chủ mới đang gắn với farm khác?
                $newOwnerOnOtherFarm =
                    $newOwner->farm_id && $newOwner->farm_id !== $farm->id;

                // Nếu chủ mới đang LÀ CHỦ farm khác → cần confirm; khi qua, gỡ
                // chủ farm kia (mồ côi). Lock farm kia để tránh race.
                if ($newOwnerOnOtherFarm && $newOwner->farm_role === 'owner') {
                    if (empty($data['confirm_warnings'])) {
                        $other = Farm::find($newOwner->farm_id);
                        throw new \DomainException("Customer đang là chủ farm \"{$other?->name}\". Tick \"Tôi đã hiểu\" để chuyển — farm đó sẽ tạm thời KHÔNG có chủ cho tới khi bạn gán chủ mới.");
                    }
                    $orphanFarm = Farm::lockForUpdate()->find($newOwner->farm_id);
                    if ($orphanFarm) {
                        $orphanFarm->owner_customer_id = null;
                        $orphanFarm->save();
                    }
                    // farm_id của newOwner được gán lại = $farm->id bên dưới →
                    // tự động rời farm cũ.
                }
                // Nếu chủ mới là NHÂN VIÊN farm khác → chuyển ngay (không cần
                // confirm). Cập nhật farm_id bên dưới tự động đưa họ rời farm cũ.

                // 1) Chủ cũ → Admin cùng farm (giữ Hub access + quyền vận hành, mất payout).
                if ($oldOwner) {
                    $oldOwner->farm_role = 'admin';
                    // farm_id giữ nguyên = farm->id; role/status giữ nguyên.
                    // farm_bank_* KHÔNG xoá — dữ liệu lịch sử per-customer.
                    $oldOwner->save();
                }

                // 2) Chủ mới → Owner.
                $newOwner->farm_id             = $farm->id;
                $newOwner->farm_role           = 'owner';
                $newOwner->role                = 'farm_partner';
                $newOwner->farm_partner_status = 'approved';
                $newOwner->save();

                // 3) Cập nhật canonical owner trên farm.
                $farm->owner_customer_id = $newOwner->id;
                $farm->save();
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('farms.show', $farm->id)
            ->with('success', $wasOwnerless
                ? 'Đã gán chủ farm thành công — nhớ cập nhật thông tin TK ngân hàng của chủ mới trước payout kế tiếp.'
                : 'Đã chuyển chủ farm thành công. Chủ cũ đã trở thành quản lý (giữ quyền vận hành) — nhớ cập nhật thông tin TK ngân hàng của chủ mới trước payout kế tiếp.');
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
