<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FarmPartner;
use Illuminate\Http\Request;

class FarmPartnerAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $query = FarmPartner::with('customer')->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $partners = $query->paginate(20);

        return view('admin.farm-partners.index', compact('partners'));
    }

    public function create()
    {
        $customers = Customer::whereDoesntHave('farmPartner')->orderBy('name')->get();
        return view('admin.farm-partners.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id|unique:farm_partners,customer_id',
            'farm_name'   => 'nullable|string|max:255',
            'note'        => 'nullable|string|max:500',
        ]);

        FarmPartner::create([
            'customer_id' => $request->customer_id,
            'farm_name'   => $request->farm_name,
            'note'        => $request->note,
            'status'      => 'active',
        ]);

        return redirect()->route('farm-partners.index')->with('success', 'Đã thêm farm-partner thành công');
    }

    public function show(FarmPartner $farmPartner)
    {
        $farmPartner->load('customer');
        return view('admin.farm-partners.show', compact('farmPartner'));
    }

    public function edit(FarmPartner $farmPartner)
    {
        return view('admin.farm-partners.edit', compact('farmPartner'));
    }

    public function update(Request $request, FarmPartner $farmPartner)
    {
        $request->validate([
            'farm_name' => 'nullable|string|max:255',
            'note'      => 'nullable|string|max:500',
        ]);

        $farmPartner->update($request->only('farm_name', 'note'));

        return redirect()->route('farm-partners.index')->with('success', 'Đã cập nhật thông tin farm-partner');
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $farmPartner = FarmPartner::findOrFail($id);
        $farmPartner->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Đã cập nhật trạng thái farm-partner');
    }

    public function destroy(FarmPartner $farmPartner)
    {
        $farmPartner->delete();
        return redirect()->route('farm-partners.index')->with('success', 'Đã xoá farm-partner');
    }
}
