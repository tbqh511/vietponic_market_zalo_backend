<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = Voucher::query();
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->active === '1');
        }

        $vouchers = $query->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        $voucher = new Voucher([
            'discount_type'         => Voucher::TYPE_PERCENT,
            'max_uses_per_customer' => 1,
            'is_active'             => true,
            'is_public'             => true,
        ]);
        return view('admin.vouchers.create', compact('voucher'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['code'] = strtoupper($data['code']);

        Voucher::create($data);
        return redirect()->route('vouchers.index')->with('success', 'Đã tạo mã giảm giá');
    }

    public function show($id)
    {
        $voucher = Voucher::with(['redemptions.order:id,customer_id,total,created_at'])->findOrFail($id);
        return view('admin.vouchers.show', compact('voucher'));
    }

    public function edit($id)
    {
        $voucher = Voucher::findOrFail($id);
        return view('admin.vouchers.edit', compact('voucher'));
    }

    public function update(Request $request, $id)
    {
        $voucher = Voucher::findOrFail($id);
        $data = $this->validateData($request, $voucher->id);
        $data['code'] = strtoupper($data['code']);

        $voucher->fill($data)->save();
        return redirect()->route('vouchers.show', $voucher->id)->with('success', 'Đã cập nhật mã giảm giá');
    }

    public function destroy($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();
        return redirect()->route('vouchers.index')->with('success', 'Đã xoá mã giảm giá');
    }

    public function toggleActive($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->is_active = !$voucher->is_active;
        $voucher->save();
        return back()->with('success', 'Đã đổi trạng thái mã giảm giá');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'                  => [
                'required', 'string', 'max:50',
                Rule::unique('vouchers', 'code')->ignore($ignoreId),
            ],
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:1000',
            'discount_type'         => 'required|in:percent,fixed,free_shipping',
            'discount_value'        => 'required|numeric|min:0|max:99999999',
            'max_discount_amount'   => 'nullable|integer|min:0',
            'min_order_amount'      => 'nullable|integer|min:0',
            'max_uses'              => 'nullable|integer|min:0',
            'max_uses_per_customer' => 'required|integer|min:0',
            'valid_from'            => 'nullable|date',
            'valid_to'              => 'nullable|date|after_or_equal:valid_from',
            'is_active'             => 'sometimes|boolean',
            'is_public'             => 'sometimes|boolean',
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'is_public' => $request->boolean('is_public'),
        ];
    }
}
