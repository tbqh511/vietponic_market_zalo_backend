<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use App\Models\ZaloCategory;
use App\Models\ZaloProduct;
use Illuminate\Http\Request;

class FrontEndHomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $newestProducts = ZaloProduct::with('category')
            ->whereHas('category', fn($q) => $q->where('name', 'Rau lá & rau thủy canh'))
            ->orderBy('id', 'DESC')
            ->take(8)
            ->get();

        $allCategories = ZaloCategory::orderBy('id')->get();

        $featuredCategories = ZaloCategory::withCount('products')
            ->orderBy('id')
            ->take(6)
            ->get();

        $exploreProducts = ZaloProduct::with('category')
            ->orderBy('id', 'DESC')
            ->take(8)
            ->get();

        return view('frontend_home', [
            'newestProducts'     => $newestProducts,
            'allCategories'      => $allCategories,
            'featuredCategories' => $featuredCategories,
            'exploreProducts'    => $exploreProducts,
        ]);
    }

    /**
     * Hiển thị một chính sách (điều khoản, vận chuyển, thanh toán, bảo mật...)
     * theo slug, dùng layout landing page. 404 nếu không tồn tại hoặc đang ẩn.
     */
    public function showPolicy($slug)
    {
        $policy = Policy::active()->where('slug', $slug)->firstOrFail();

        return view('policy_show', [
            'policy' => $policy,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function about()
    {
        $infos = [
            ['title' => 'Loại rau sạch', 'value' => ZaloProduct::count()],
            ['title' => 'Khách hài lòng', 'value' => 2400],
            ['title' => 'Năm kinh nghiệm', 'value' => 5],
            ['title' => 'Đơn giao mỗi ngày', 'value' => 150],
        ];

        return view('about', [
            'infos' => $infos,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        return view('product');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
