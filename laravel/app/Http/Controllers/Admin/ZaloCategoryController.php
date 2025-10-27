<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloCategory;
use Illuminate\Http\Request;

class ZaloCategoryController extends Controller
{
    public function index()
    {
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.zalo_categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:1024',
        ]);

        // model uses non-incrementing id (imported from mock). Generate next id.
        $max = ZaloCategory::max('id');
        $id = ($max ? $max : 0) + 1;

        $data['id'] = $id;

        ZaloCategory::create($data);

        return redirect()->route('zalo-categories.index')->with('success', 'Category created');
    }

    public function edit($id)
    {
        $category = ZaloCategory::findOrFail($id);
        return view('admin.zalo_categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $category = ZaloCategory::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:1024',
        ]);
        $category->update($data);
        return redirect()->route('zalo-categories.index')->with('success', 'Category updated');
    }

    public function destroy($id)
    {
        $category = ZaloCategory::findOrFail($id);
        $category->delete();
        return redirect()->route('zalo-categories.index')->with('success', 'Category deleted');
    }
}
