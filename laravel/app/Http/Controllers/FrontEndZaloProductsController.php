<?php

namespace App\Http\Controllers;

use App\Models\ZaloProduct;
use App\Models\ZaloCategory;
use Illuminate\Http\Request;

class FrontEndZaloProductsController extends Controller
{
    /**
     * Display a listing of the zalo products.
     */
    public function index(Request $request)
    {
        $query = ZaloProduct::query();

        // Filter by category if provided
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by search term if provided
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('id')->with('category')->paginate(12);
        $categories = ZaloCategory::orderBy('id')->get();

        return view('frontend.zalo_products.index', compact('products', 'categories'));
    }

    /**
     * Display the specified zalo product.
     */
    public function show($id)
    {
        $product = ZaloProduct::with('category')->findOrFail($id);

        // Get related products from same category
        $relatedProducts = ZaloProduct::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('category')
            ->limit(4)
            ->get();

        return view('frontend.zalo_products.show', compact('product', 'relatedProducts'));
    }
}