<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\parameter;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\LocationsStreet;
use App\Models\LocationsWard;
use App\Models\ProductType;
use App\Models\Product;
use PHPUnit\Framework\MockObject\Rule\Parameters;

class FrontEndPropertiesController extends Controller
{
    /**
     * Display the detail of a product.
     */
    public function show(string $slug)
    {
        // Fetch the product based on the slug
        $product = Property::where('slug', $slug)->first();

        // Return the product detail view with the necessary data
        return view('frontend_properties_detail', ['property' => $product]);
    }
    /**
     * Display the detail of a property by its ID.
     */
    public function getPropertyById(int $id)
    {
        $categories = Category::orderBy('category')->get();

        // Get the district code from configuration
        $districtCode = config('location.district_code');
        // If there's a district code, get the list of wards in that district
        $locationsWards = ($districtCode != null) ? LocationsWard::where('district_code', $districtCode)->get()->sortBy('full_name') : LocationsWard::all();

        // Fetch the property based on the ID
        $property = Property::findOrFail($id);
        //dd($property);

        // Set parameters for the product query
        $offset = 0;
        $limit = 5;
        $sort = 'updated_at';
        $order = 'DESC';

        // Lấy loại của property hiện tại
        $category_id = $property->category_id;
        $ward_code = $property->ward_code;


        $relatedProducts = Property::where('category_id', $category_id)
            ->orwhere('ward_code', $ward_code)
            ->where('id', '!=', $property->id) // Loại bỏ sản phẩm hiện tại
            ->with('customer')
            ->with('user')
            ->with('category:id,category,image')
            ->with('assignfacilities.outdoorfacilities')
            ->with('favourite')
            ->with('parameters')
            ->with('interested_users')
            ->with('ward')
            ->with('street')
            ->with('host')
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        // Set parameters for highlighted Products
        $limit = 5;
        $sort = 'total_click'; // Sắp xếp theo số lượt click
        $order = 'DESC';

        $highlightedProducts = Property::orderBy($sort, $order)
            ->take($limit)
            ->get();

        // Tăng giá trị của cột total_click
        $property->increment('total_click');

        //dd(Property::where('added_by', '2')->get()->count());

        // Return the property detail view with the necessary data
        return view('frontend_properties_detail', [
            'property' => $property,
            'relatedProducts' => $relatedProducts,
            'highlightedProducts' => $highlightedProducts,
            'categories' => $categories,
            'locationsWards' => $locationsWards,
        ]);
    }

    /**
     * Display a listing of the products with search variables: category, ward, street, id.
     */
    public function index(Request $request)
    {
        // Get the list of streets in the areas
        $locationsStreets = LocationsStreet::all();

        // Get the district code from configuration
        $districtCode = config('location.district_code');
        // If there's a district code, get the list of wards in that district
        $locationsWards = ($districtCode != null) ? LocationsWard::where('district_code', $districtCode)->get()->sortBy('full_name') : LocationsWard::all();

        // Get the list of product categories sorted by category in ascending order
        $categories = Category::orderBy('category')->get();

        // get the list legals of properties         
        $legalsParameter = Parameter::find(config('global.legal')); // Lấy bản ghi theo config
        $legals = $legalsParameter->type_values;

        // get the list directions of properties         
        $directionsParameter = Parameter::find(config('global.direction')); // Lấy bản ghi theo config
        $directions = $directionsParameter->type_values;

        // Get search parameters
        $idInput = $request->input('_token');
        $categoryInput = $request->input('category');
        $wardInput = $request->input('ward');
        $streetInput = $request->input('street');
        $textInput = $request->input('text');
        $propertyTypeInput = $request->input('propery_type');
        $priceRangeInput = $request->input('price-range2');
        $legalInput = $request->input('legal');
        $directionInput = $request->input('direction');
        $areaInput = $request->input('area');
        $numberFloorInput = $request->input('number_floor');
        $numberRoomInput = $request->input('number_room');
        $sortStatus = $request->input('sort_status');

        // Query to fetch properties based on search pndiarameters
        $propertiesQuery = Property::query();
        // Add cotions to query based on search parameters

        if (!empty($categoryInput)) {
            $propertiesQuery->whereHas('category', function ($query) use ($categoryInput) {
                $query->where('category', $categoryInput);
            });
        }

        if (!empty($wardInput)) {
            $propertiesQuery->where('ward_code', $wardInput);
        }

        if (!empty($streetInput)) {
            $propertiesQuery->where('street_code', $streetInput);
        }

        if (!empty($textInput)) {
            // Tách chuỗi code thành các phần
            $parts = explode('_', $textInput);
            // Lấy id từ phần tử cuối cùng của mảng parts
            $propertiesQuery->where('id', end($parts));
        }

        if (isset($propertyTypeInput)) {
            if ($propertyTypeInput == '1') {
                // Xử lý khi người dùng chọn "Cho Thuê"
                $propertiesQuery->where('propery_type', 1);
            } elseif ($propertyTypeInput == '0') {
                // Xử lý khi người dùng chọn "Bán"
                $propertiesQuery->where('propery_type', 0);
            } else {
                // Xử lý khi người dùng chọn "Cho thuê & Bán"
                // Không cần thêm điều kiện gì vì đã xử lý các trường hợp này trước đó
            }
        }
        

        if (!empty($priceRangeInput)) {
            // Tách giá trị thành mảng các khoảng giá
            $priceRanges = explode(';', $priceRangeInput);
            // Lấy giá trị tối thiểu và tối đa của khoảng giá
            $minPrice = $priceRanges[0];
            $maxPrice = $priceRanges[1];

            // Đảm bảo giá trị của $minPrice và $maxPrice là số nguyên
            $minPrice = intval($minPrice);
            $maxPrice = intval($maxPrice);

            //dd($maxPrice == config('global.max_price'),$minPrice,$maxPrice,config('global.max_price'),$maxPrice == config('global.max_price'));
            // Thêm điều kiện vào truy vấn để lấy các bất động sản trong khoảng giá
            if ($maxPrice == config('global.max_price')) {
                // Truy vấn các bất động sản có giá lớn hơn hoặc bằng $minPrice
                $propertiesQuery->where('price', '>', $minPrice);
            } else {
                // Truy vấn các bất động sản trong khoảng giá từ $minPrice đến $maxPrice
                $propertiesQuery->whereBetween('price', [$minPrice, $maxPrice]);
            }
        }

        if (!empty($areaInput)) {
            // Tách giá trị range diện tích thành mảng
            $areaRange = explode(';', $areaInput);
            $minArea = $areaRange[0];
            $maxArea = $areaRange[1];

            // Thêm điều kiện vào truy vấn để lấy các bất động sản trong khoảng diện tích
            if ($maxArea == config('global.max_area')) {
                // Truy vấn các bất động sản có diện tích lớn hơn $minArea
                $propertiesQuery->whereHas('assignParameter', function ($query) use ($minArea) {
                    $query->where('parameter_id', config('global.area'))
                        ->where('value', '>', $minArea);
                });
            } else {
                // Truy vấn các bất động sản trong khoảng diện tích từ $minArea đến $maxArea
                $propertiesQuery->whereHas('assignParameter', function ($query) use ($minArea, $maxArea) {
                    $query->where('parameter_id', config('global.area'))
                        ->whereBetween('value', [$minArea, $maxArea]);
                });
            }
        }

        if (!empty($legalInput)) {
            $propertiesQuery->whereHas('assignParameter', function ($query) use ($legalInput) {
                $query->where('parameter_id', config('global.legal'))
                    ->where('value', $legalInput);
            });
        }

        if (!empty($directionInput)) {
            $propertiesQuery->whereHas('assignParameter', function ($query) use ($directionInput) {
                $query->where('parameter_id', config('global.direction'))
                    ->where('value', $directionInput);
            });
        }



        if (!empty($numberFloorInput)) {
            if ($numberRoomInput != "0") {
                $propertiesQuery->whereHas('assignParameter', function ($query) use ($numberFloorInput) {
                    $query->where('parameter_id', config('global.number_floor'))
                        ->where('value', $numberFloorInput);
                });
            }
        }

        if (!empty($numberRoomInput)) {
            if ($numberRoomInput != "0") {
                $propertiesQuery->whereHas('assignParameter', function ($query) use ($numberRoomInput) {
                    $query->where('parameter_id', config('global.number_room'))
                        ->where('value', $numberRoomInput);
                });
            }
        }

        if ($sortStatus == 'price_asc') {
            $propertiesQuery->orderBy('price', 'asc');
        } elseif ($sortStatus == 'price_desc') {
            $propertiesQuery->orderBy('price', 'desc');
        } elseif ($sortStatus == 'view_count') {
            $propertiesQuery->orderBy('total_click', 'desc');
        }

        //Only get active properties

        // Lấy các tham số tìm kiếm
        $searchParams = $request->except('_token', 'page');
        // Lấy danh sách bất động sản dựa trên truy vấn
        $properties = $propertiesQuery->where('status', '1')->paginate(6)->appends($searchParams);

        //dd($areaInput, $numberFloorInput, $numberFloorInput);
        //dd($propertyTypeInput);
        // Define the search result message
        $searchResult = $this->generateSearchResultMessage($textInput, $propertyTypeInput, $priceRangeInput, $legalInput, $directionInput, $areaInput, $numberFloorInput, $numberRoomInput, $sortStatus, $categoryInput, $wardInput, $streetInput);

        // Pass the properties and search result message to the view
        return view('frontend_properties_listing', compact('properties', 'searchResult', 'locationsStreets', 'locationsWards', 'categories', 'legals', 'directions'));
    }

    private function generateSearchResultMessage($textInput, $propertyTypeInput, $priceRangeInput, $legalInput, $directionInput, $areaInput, $numberFloorInput, $numberRoomInput, $sortStatus, $categoryInput, $wardInput, $streetInput)
    {
        $searchResult = "Kết quả cho:\"";

        // $textInput = $request->input('text');
        if (!empty($textInput)) {
            $searchResult .= "Tìm \"" . $textInput . "\", ";
        }

        //$propertyTypeInput = $request->input('propery_type');
        if (!empty($propertyTypeInput)) {
            if ($propertyTypeInput == 0)
                $searchResult .= "Bán, ";
            else
                $searchResult .= "Cho thuê, ";
        }

        if (!empty($legalInput)) {
            $searchResult .= config('global.legal_title') . ": " . $legalInput . "\", ";
        }
        // $directionInput = $request->input('direction');
        if (!empty($directionInput)) {
            $searchResult .= config('global.direction_title') . ": " . $directionInput . ", ";
        }

        if (!empty($categoryInput)) {
            $searchResult .= "Loại BDS: " . $categoryInput . ", ";
        }

        if (!empty($streetInput)) {
            $street = LocationsStreet::where('code', $streetInput)->first();
            if ($street) {
                $searchResult .= 'đường ' . $street->street_name . ", ";
            }
        }

        if (!empty($wardInput)) {
            $ward = LocationsWard::where('code', $wardInput)->first();
            if ($ward) {
                $searchResult .= $ward->full_name . ", ";
            }
        }
        // Loại bỏ ký tự phẩy và khoảng trắng cuối cùng
        $searchResult = rtrim($searchResult, ", ");
        // Thêm chuỗi đuôi
        //dd($searchResult );
        if ($searchResult == "Kết quả cho:\"")
            $searchResult = "Kết quả cho: \"Tp Đà Lạt\"";
        else
            $searchResult .= ", Tp Đà Lạt\"";

        return $searchResult;
        // // $priceRangeInput = $request->input('price-range2');
        // if (!empty($priceRangeInput)) {
        //     // Tách giá trị thành mảng các khoảng giá
        //     $priceRanges = explode(';', $priceRangeInput);
        //     // Lấy giá trị tối thiểu và tối đa của khoảng giá
        //     $minPrice = $priceRanges[0];
        //     $maxPrice = $priceRanges[1];
        //     if($maxPrice == config('global.max_price'))
        //         $searchResult .= config('global.max_price').": cao hơn ".$minPrice;
        //     else
        //         $searchResult .= config('global.max_price').": \"\"".$minPrice." - ".$maxPrice."\"\", ";
        // }
        // $legalInput = $request->input('legal');

        // // $areaInput = $request->input('area');
        // if (!empty($areaInput)) {
        //     // Tách giá trị thành mảng các khoảng giá
        //     $areaRanges = explode(';', $areaInput);
        //     // Lấy giá trị tối thiểu và tối đa của khoảng giá
        //     $minArea = $areaRanges[0];
        //     $maxArea = $areaRanges[1];
        //     if($maxArea == config('global.max_area'))
        //         $searchResult .= config('global.area_title').": lớn hơn". $minArea;
        //     else
        //         $searchResult .= config('global.area_title').": \"\"".$minArea." - ".$maxArea."\"\", ";
        // }
        // // $numberFloorInput = $request->input('number_floor');
        // if (!empty($numberFloorInput)) {
        //     $searchResult .=  config('global.number_floor_title').": \'".$numberFloorInput."\", ";
        // }
        // // $numberRoomInput = $request->input('number_room');
        // if (!empty($numberRoomInput)) {
        //     $searchResult .=  config('global.number_room_title').": \'".$numberRoomInput."\", ";
        // }
        // // $sortStatus = $request->input('sort_status');
        // if (!empty($sortStatus)) {
        //     if ($sortStatus == 'view_count') {
        //         $searchResult .= "Sắp xếp theo độ phổ biến, ";
        //     } elseif ($sortStatus == 'price_asc') {
        //         $searchResult .= "Sắp xếp theo giá: thấp đến cao, ";
        //     } elseif ($sortStatus == 'price_desc') {
        //         $searchResult .= "Sắp xếp theo giá: cao đến thấp, ";
        //     }
        // }
    }

    /**
     * Get street suggestions for autocomplete.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function autocompleteStreet(Request $request)
    {
        $term = $request->input('term');

        // Query the streets based on the search term
        $streets = LocationsStreet::where('street_name', 'like', '%' . $term . '%')->pluck('street_name');

        return response()->json($streets);
    }
}
