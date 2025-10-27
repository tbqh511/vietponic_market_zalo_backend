<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZaloProduct;
use App\Models\ZaloCategory;
use Illuminate\Http\Request;

class ZaloProductController extends Controller
{
    public function index(Request $request)
    {
        $query = ZaloProduct::query();
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        $products = $query->orderBy('id')->with('category')->get();
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:zalo_categories,id',
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string|max:1024',
            'detail' => 'nullable|string',
        ]);

        // generate id similar to categories (mock uses explicit ids)
        $max = ZaloProduct::max('id');
        $id = ($max ? $max : 0) + 1;
        $data['id'] = $id;
        $data['price'] = $data['price'] ?? 0;

        ZaloProduct::create($data);
        return redirect()->route('zalo-products.index')->with('success', 'Product created');
    }

    public function edit($id)
    {
        $product = ZaloProduct::findOrFail($id);
        $categories = ZaloCategory::orderBy('id')->get();
        return view('admin.zalo_products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $product = ZaloProduct::findOrFail($id);
        $data = $request->validate([
            'category_id' => 'nullable|exists:zalo_categories,id',
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string|max:1024',
            'detail' => 'nullable|string',
        ]);
        $product->update($data);
        return redirect()->route('zalo-products.index')->with('success', 'Product updated');
    }

    public function destroy($id)
    {
        $product = ZaloProduct::findOrFail($id);
        $product->delete();
        return redirect()->route('zalo-products.index')->with('success', 'Product deleted');
    }
}
