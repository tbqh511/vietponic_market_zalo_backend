<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CrmHost;
use App\Models\Customer;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\LocationsStreet;
use App\Models\LocationsWard;
use App\Models\ProductType;
use App\Models\Product;

class FrontEndHomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get the list of streets in the areas
        $locationsStreets = LocationsStreet::all();

        // Get the district code from configuration
        $districtCode = config('location.district_code');
        // If there's a district code, get the list of wards in that district
        $locationsWards = ($districtCode != null) ? LocationsWard::where('district_code', $districtCode)->orderByRaw("CASE
        WHEN full_name LIKE 'phường%' THEN 1
        WHEN full_name LIKE 'Xã%' THEN 2
        ELSE 3 END, CAST(SUBSTRING_INDEX(full_name, ' ', -1) AS UNSIGNED), full_name")->get() : LocationsWard::orderByRaw("CASE
        WHEN full_name LIKE 'phường%' THEN 1
        WHEN full_name LIKE 'Xã%' THEN 2
        ELSE 3 END, CAST(SUBSTRING_INDEX(full_name, ' ', -1) AS UNSIGNED), full_name")->get();

        // Get the list of product categories
        $categories = Category::all();

        // Get list top agent
        $agents = Customer::withCount('property')->orderBy('property_count', 'desc')->get();


        // Set parameters for the product query
        $offset = 0;
        $limit = 6;
        $sort = 'updated_at';
        $order = 'DESC';

        // Get the list of newest products
        $newestProducts = Property::with('customer')
            ->with('user')
            ->with('category:id,category,image')
            ->with('assignfacilities.outdoorfacilities')
            ->with('favourite')
            ->with('parameters')
            ->with('interested_users')
            ->with('ward')
            ->with('street')
            ->with('host')
            ->where('status','1')
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        //get info for homepage
        $infos= [
            [
                'title' => 'Bất động sản',
                'value' => Property::count()
            ],
            [
                'title' => 'Đối tác',
                'value' => Customer::count()
            ],
            [
                'title' => 'Khách hàng hài lòng',
                'value' => CrmHost::count()
            ],
            [
                'title' => 'Bất động sản mới trong tuần',
                'value' => Property::where('created_at', '>=', Carbon::now()->subDays(7))->count()
            ],
            // Các cặp title và value khác có thể thêm vào đây
        ];

        //dd($newestProducts[0]->parameters[0]->pivot->pivot_value);
        //dd($newestProducts[0]->parameters[0]->pivot->value);
        // $valueOfParameterId15 = $newestProducts[0]->parameters->where('name', config('global.area'))->first()->pivot->value;
        // dd($valueOfParameterId15);
        //dd($newestProducts[2]->number_floor);
        //dd(config('global.number_floor'));
        //dd($newestProducts);
        // $property = Property::with('customer')->with('user')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->with('ward')->with('street')->with('host')->get();
        // Return the frontend_home view with the necessary data

        return view('frontend_home', [
            'locationsStreets' => $locationsStreets,
            'locationsWards' => $locationsWards,
            'categories' => $categories,
            'newestProducts' => $newestProducts,
            'agents' => $agents,
            'infos' => $infos,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function about()
    {
        // Get list top agent
        $agents = Customer::withCount('property')->orderBy('property_count', 'desc')->get();

        //get info for homepage
        $infos= [
            [
                'title' => 'Bất động sản',
                'value' => Property::count()
            ],
            [
                'title' => 'Đối tác',
                'value' => Customer::count()
            ],
            [
                'title' => 'Khách hàng hài lòng',
                'value' => CrmHost::count()
            ],
            [
                'title' => 'Bất động sản mới trong tuần',
                'value' => Property::where('created_at', '>=', Carbon::now()->subDays(7))->count()
            ],
            // Các cặp title và value khác có thể thêm vào đây
        ];


        return view('about', [
            'agents' => $agents,
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
