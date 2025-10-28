<?php

namespace App\Http\Controllers;

use App\Models\ZaloProduct;
use App\Models\ZaloCategory;
use Illuminate\Http\Request;

class FrontEndZaloProductsController extends Controller
{
    /**
     * Display a listing of Zalo products.
     */
    public function index(Request $request)
    {
        $query = ZaloProduct::query()->with('category');

        // Filter by category if provided
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Search by name if provided
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort options
        $sort = $request->get('sort', 'id');
        $order = $request->get('order', 'desc');

        if ($sort == 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sort == 'price_desc') {
            $query->orderBy('price', 'desc');
        } elseif ($sort == 'name') {
            $query->orderBy('name', $order);
        } else {
            $query->orderBy('id', 'desc');
        }

        $products = $query->paginate(12)->appends($request->query());

        // Get categories for filter
        $categories = ZaloCategory::orderBy('name')->get();

        return view('frontend_zalo_products', compact('products', 'categories'));
    }

    /**
     * Display the detail of a Zalo product.
     */
    public function show($id)
    {
        $product = ZaloProduct::with('category')->findOrFail($id);

        // Get related products from same category
        $relatedProducts = ZaloProduct::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return view('frontend_zalo_product_detail', compact('product', 'relatedProducts'));
    }
}