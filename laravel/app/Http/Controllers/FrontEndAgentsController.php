<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\LocationsWard;
use App\Models\Property;
use Illuminate\Http\Request;

class FrontEndAgentsController extends Controller
{
    public function getAgentById(Request $request, int $id)
    {
        // Get the district code from configuration
        $districtCode = config('location.district_code', null);

        // Get the list of wards based on the district code
        $locationsWards = LocationsWard::when($districtCode, function ($query) use ($districtCode) {
            return $query->where('district_code', $districtCode);
        })->get()->sortBy('full_name');

        // Get category for header section
        $categories = Category::orderBy('category')->get();

        // Get agent or return 404 if not found
        $agent = Customer::findOrFail($id);

        // Get properties paging by agent ID
        $propertiesQuery = Property::where('added_by', $id)->where('status', '1');
        $properties = $propertiesQuery->paginate(6)->appends($request->except('_token', 'page'));

        return view('frontend_agents_detail', [
            'agent' => $agent,
            'properties' => $properties,
            'categories' => $categories,
            'locationsWards' => $locationsWards,
        ]);
    }

    /**
     * Display a listing of the properties with search variables: category, ward, street, id.
     */
    public function index(Request $request)
    {
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

        //$agents = Customer::orderByDesc('customer_total_post')->limit(5)->get();

        $agents = Customer::limit(5)->get();

        return view('frontend_agents_listing',[
            'agents' => $agents,
            'categories'=> $categories,
            'locationsWards' => $locationsWards,
        ]);
    }
}
