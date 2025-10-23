<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->where('is_active', true);
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $perPage = max(10, (int)$request->query('per_page', 20));
        $data = $query->paginate($perPage);
        return response()->json($data);
    }
}
