<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PolicyController extends Controller
{
    public function index()
    {
        $policies = Policy::orderBy('sort_order')->orderBy('id')->get();
        return view('admin.policies.index', compact('policies'));
    }

    public function create()
    {
        $policy = new Policy([
            'is_active'  => true,
            'sort_order' => (int) Policy::max('sort_order') + 1,
        ]);
        return view('admin.policies.create', [
            'policy'    => $policy,
            'suggested' => Policy::SUGGESTED,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Policy::create($data);
        return redirect()->route('policies.index')->with('success', 'Đã tạo chính sách');
    }

    public function edit($id)
    {
        $policy = Policy::findOrFail($id);
        return view('admin.policies.edit', [
            'policy'    => $policy,
            'suggested' => Policy::SUGGESTED,
        ]);
    }

    public function update(Request $request, $id)
    {
        $policy = Policy::findOrFail($id);
        $data = $this->validateData($request, $policy->id);
        $policy->update($data);
        return redirect()->route('policies.index')->with('success', 'Đã cập nhật chính sách');
    }

    public function destroy($id)
    {
        $policy = Policy::findOrFail($id);
        $policy->delete();
        return redirect()->route('policies.index')->with('success', 'Đã xoá chính sách');
    }

    /**
     * Validate + chuẩn hoá dữ liệu. Tự sinh slug từ title nếu để trống.
     */
    private function validateData(Request $request, $ignoreId = null): array
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'slug'       => [
                'nullable', 'string', 'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('policies', 'slug')->ignore($ignoreId),
            ],
            'content'    => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
        ], [
            'slug.regex' => 'Slug chỉ gồm chữ thường, số và dấu gạch ngang (vd: chinh-sach-van-chuyen).',
        ]);

        $data['slug']       = $data['slug'] ?: Str::slug($data['title']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active');

        return $data;
    }
}
