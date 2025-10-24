<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Article;
use App\Models\AssignParameters;
use App\Models\Category;
use App\Models\CrmHost;
use App\Models\Customer;
use App\Models\Favourite;

use App\Models\InterestedUser;
use App\Models\Language;
use App\Models\LocationsStreet;
use App\Models\LocationsWard;
use App\Models\Notifications;
use App\Models\Package;
use App\Models\parameter;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Models\PropertyLegalImage;
use App\Models\PropertysInquiry;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Type;
use App\Models\Usertokens;


use App\Models\User;
use App\Models\Chats;
use Carbon\CarbonInterface;
use App\Models\UserPurchasedPackage;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
// use GuzzleHttp\Client;
use App\Models\report_reasons;
use App\Models\user_reports;

use Intervention\Image\ImageManagerStatic as Image;

use Illuminate\Support\Str;
use kornrunner\Blurhash\Blurhash;
use App\Libraries\Paypal;
use App\Models\Payments;
use App\Libraries\Paypal_pro;
use App\Models\AssignedOutdoorFacilities;
use App\Models\OutdoorFacilities;
// use PayPal_Pro as GlobalPayPal_Pro;
use Tymon\JWTAuth\Claims\Issuer;

class ApiController extends Controller
{
    function update_subscription()
    {
        $data = UserPurchasedPackage::where('user_id', Auth::id())->where('end_date', Carbon::now());
        if ($data) {
            $Customer = Customer::find(Auth::id());
            $Customer->subscription = 0;
            $Customer->update();
        }
    }
    //* START :: get_system_settings   *//
    public function get_system_settings(Request $request)
    {


        $result = '';

        $result = Setting::select('type', 'data')->get();
        $data_arr = [];
        foreach ($result as $row) {
            $tempRow[$row->type] = $row->data;
        }





        if (isset($request->user_id)) {

            $data = UserPurchasedPackage::where('modal_id', $request->user_id)->where('end_date', date('d'))->where('end_date', '!=', NULL)->get();


            $customer = Customer::select('id')->where('subscription', 1)->with('user_purchased_package.package')->find($request->user_id);


            if ($customer) {
                if (count($data)) {

                    $customer->subscription = 0;
                    $customer->update();
                }

                $tempRow['subscription'] = true;
                $tempRow['package'] = $customer;
            } else {
                $tempRow['subscription'] = false;
            }
        }
        $language = Language::select('code', 'name')->get();
        $tempRow['demo_mode'] = env('DEMO_MODE');
        $tempRow['languages'] = $language;

        if (!empty($result)) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $tempRow;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: Get System Setting   *//
    //* START :: user_signup   *//
    public function user_signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'type' => 'required',
            'firebase_id' => 'required',

        ]);

        if (!$validator->fails()) {
            $type = $request->type;
            $firebase_id = $request->firebase_id;

            $user = Customer::where('firebase_id', $firebase_id)->where('logintype', $type)->get();
            if ($user->isEmpty()) {
                $saveCustomer = new Customer();
                $saveCustomer->name = isset($request->name) ? $request->name : '';
                $saveCustomer->email = isset($request->email) ? $request->email : '';
                $saveCustomer->mobile = isset($request->mobile) ? $request->mobile : '';
                // $saveCustomer->profile = isset($request->profile) ? $request->profile : '';
                $saveCustomer->fcm_id = isset($request->fcm_id) ? $request->fcm_id : '';
                $saveCustomer->logintype = isset($request->type) ? $request->type : '';
                $saveCustomer->address = isset($request->address) ? $request->address : '';
                $saveCustomer->firebase_id = isset($request->firebase_id) ? $request->firebase_id : '';
                $saveCustomer->isActive = '1';


                $destinationPath = public_path('images') . config('global.USER_IMG_PATH');
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                // image upload

                if ($request->hasFile('profile')) {
                    // dd('in');
                    $profile = $request->file('profile');
                    $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                    $profile->move($destinationPath, $imageName);
                    $saveCustomer->profile = $imageName;
                } else {
                    $saveCustomer->profile = '';
                }

                $saveCustomer->save();

                $start_date = Carbon::now();
                $package = Package::find(1);
                // dd($package->id);
                if ($package && $package->status == 1) {
                    $user_package = new UserPurchasedPackage();
                    $user_package->modal()->associate($saveCustomer);
                    $user_package->package_id = 1;
                    $user_package->start_date = $start_date;
                    $user_package->end_date = Carbon::now()->addDays($package->duration);
                    $user_package->save();

                    $saveCustomer->subscription = 1;
                    $saveCustomer->update();
                }
                // $user_package = new UserPurchasedPackage();
                // $user_package->modal()->associate($saveCustomer);
                // $user_package->package_id = 1;
                // $user_package->start_date = $start_date;
                // $user_package->end_date =  Carbon::now()->addDays(30);
                // $user_package->save();

                // $saveCustomer->subscription = 1;
                // $saveCustomer->update();

                $response['error'] = false;
                $response['message'] = 'User Register Successfully';

                $credentials = Customer::find($saveCustomer->id);
                $token = JWTAuth::fromUser($credentials);
                try {
                    if (!$token) {
                        $response['error'] = true;
                        $response['message'] = 'Login credentials are invalid.';
                    } else {
                        $credentials->api_token = $token;

                        $credentials->update();
                    }
                } catch (JWTException $e) {
                    $response['error'] = true;
                    $response['message'] = 'Could not create token.';
                }
                $response['token'] = $token;
                $response['data'] = $credentials;
            } else {
                $credentials = Customer::where('firebase_id', $firebase_id)->where('logintype', $type)->first();
                try {
                    $token = JWTAuth::fromUser($credentials);
                    if (!$token) {
                        $response['error'] = true;
                        $response['message'] = 'Login credentials are invalid.';
                    } else {
                        $credentials->api_token = $token;
                        $credentials->update();
                        //     $token_exist=Usertokens::where('fcm_id',$request->fcm_id)->count();

                        // if(!$token_exist){
                        // $user_token = new Usertokens();
                        // $user_token->customer_id = $credentials->id;
                        // $user_token->fcm_id = isset($request->fcm_id) ? $request->fcm_id : '';
                        // $user_token->api_token = '';
                        // $user_token->save();
                        // }

                    }
                } catch (JWTException $e) {
                    $response['error'] = true;
                    $response['message'] = 'Could not create token.';
                }


                $response['error'] = false;
                $response['message'] = 'Login Successfully';
                $response['token'] = $token;
                $response['data'] = $credentials;
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Please fill all data and Submit';
        }
        return response()->json($response);
    }
    //* START :: get_slider   *//
    public function get_slider(Request $request)
    {
        $tempRow = array();
        $slider = Slider::select('id', 'image', 'sequence', 'category_id', 'propertys_id')->orderBy('sequence', 'ASC')->get();
        if (!$slider->isEmpty()) {

            foreach ($slider as $row) {

                $property = Property::with('parameters')->find($row->propertys_id);



                $tempRow['id'] = $row->id;
                // $tempRow['image'] = $row->image;
                $tempRow['sequence'] = $row->sequence;
                $tempRow['category_id'] = $row->category_id;
                $tempRow['propertys_id'] = $row->propertys_id;

                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $property_img = Property::select('title_image')->find($row->propertys_id);
                    // dd($property_img);
                    $tempRow['image'] = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.SLIDER_IMG_PATH') . $row->image : $property_img->title_image;
                } else {



                    $tempRow['image'] = $property->title_image;
                }


                $promoted = Slider::where('propertys_id', $row->propertys_id)->first();
                // print_r($promoted);

                if ($promoted) {
                    $tempRow['promoted'] = true;
                } else {
                    $tempRow['promoted'] = false;
                }
                $tempRow['property_title'] = $property->title;
                $tempRow['property_price'] = $property->price;


                if ($property->propery_type == 0) {
                    $tempRow['property_type'] = "sell";
                } elseif ($property->propery_type == 1) {
                    $tempRow['property_type'] = "rent";
                } elseif ($property->propery_type == 2) {
                    $tempRow['property_type'] = "sold";
                } elseif ($property->propery_type == 3) {
                    $tempRow['property_type'] = "Rented";
                }

                $tempRow['parameters'] = [];

                foreach ($property->parameters as $res) {
                    $parameter = [
                        'id' => $res->id,
                        'name' => $res->name,

                        'value' => $res->pivot->value,
                    ];
                    array_push($tempRow['parameters'], $parameter);
                }
                $rows[] = $tempRow;
            }


            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $rows;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: get_slider   *//
    //* START :: get_categories   *//
    public function get_categories(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $categories = Category::select('id', 'category', 'image', 'parameter_types', 'order')
        ->where('status', '1')
        ->orderBy('order', 'asc');

        if (isset($request->search) && !empty($request->search)) {
            $search = $request->search;
            $categories->where('category', 'LIKE', "%$search%");
        }

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $categories->where('id', '=', $id);
        }

        $total = $categories->get()->count();
        $result = $categories->orderBy('sequence', 'ASC')->skip($offset)->take($limit)->get();

        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            foreach ($result as $row) {
                $row->parameter_types = parameterTypesByCategory($row->id);
            }
            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: get_categories   *//
    //* START :: update_profile   *//
    public function update_profile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required',
        ]);


        if (!$validator->fails()) {
            $id = $request->userid;

            $customer = Customer::find($id);

            if (!empty($customer)) {
                if (isset($request->name)) {
                    $customer->name = ($request->name) ? $request->name : '';
                }
                if (isset($request->email)) {
                    $customer->email = ($request->email) ? $request->email : '';
                }
                if (isset($request->mobile)) {
                    $customer->mobile = ($request->mobile) ? $request->mobile : '';
                }

                if (isset($request->fcm_id)) {
                    $token_exist = Usertokens::where('fcm_id', $request->fcm_id)->get();
                    if (!count($token_exist)) {
                        $user_token = new Usertokens();
                        $user_token->customer_id = $customer->id;
                        $user_token->fcm_id = isset($request->fcm_id) ? $request->fcm_id : '';
                        $user_token->api_token = '';
                        $user_token->save();
                    }
                    $customer->fcm_id = ($request->fcm_id) ? $request->fcm_id : '';
                }
                if (isset($request->address)) {
                    $customer->address = ($request->address) ? $request->address : '';
                }
                if (isset($request->address)) {
                    $customer->address = ($request->address) ? $request->address : '';
                }

                if (isset($request->firebase_id)) {
                    $customer->firebase_id = ($request->firebase_id) ? $request->firebase_id : '';
                }
                if (isset($request->notification)) {
                    $customer->notification = $request->notification;
                }

                $destinationPath = public_path('images') . config('global.USER_IMG_PATH');
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                // image upload


                if ($request->hasFile('profile')) {
                    // dd('in');
                    $old_image = $customer->profile;

                    $profile = $request->file('profile');
                    $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                    if ($profile->move($destinationPath, $imageName)) {
                        $customer->profile = $imageName;
                        if ($old_image != '') {
                            if (file_exists(public_path('images') . config('global.USER_IMG_PATH') . $old_image)) {
                                unlink(public_path('images') . config('global.USER_IMG_PATH') . $old_image);
                            }
                        }
                    }
                }
                $customer->update();


                $response['error'] = false;
                $response['data'] = $customer;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: update_profile   *//
    //* START :: get_user_by_id   *//
    public function get_user_by_id(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required',
        ]);

        if (!$validator->fails()) {
            $id = $request->userid;

            $customer = Customer::find($id);
            if (!empty($customer)) {

                $response['error'] = false;
                $response['data'] = $customer;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: get_user_by_id   *//
    //* START :: get_property   *//
    public function get_property(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);
        DB::enableQueryLog();
        $property = Property::with('customer')->with('user')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->with('ward')->with('street')->with('host');


        $property_type = $request->property_type; //0 : Buy 1:Rent
        $max_price = $request->max_price;
        $min_price = $request->min_price;
        $top_rated = $request->top_rated;

        $userid = $request->userid;
        $posted_since = $request->posted_since;
        $category_id = $request->category_id;
        $id = $request->id;
        $country = $request->country;
        $state = $request->state;
        $city = $request->city;

        $furnished = $request->furnished;
        $parameter_id = $request->parameter_id;

        //HuyTBQ: Add address columns for propertys table
        $street_number = $request->street_number;
        $street_code = $request->street_code;
        $ward_code = $request->ward_code;
        $host_id = $request->host_id;
        $price_sort = $request->price_sort;

        // HuyTBQ: Add sorting logic for price
        if (isset($price_sort)) {
            if ($price_sort == 0) {
                // Sắp xếp giá từ cao xuống thấp
                $property = $property->orderBy('price', 'DESC');
            } elseif ($price_sort == 1) {
                // Sắp xếp giá từ thấp lên cao
                $property = $property->orderBy('price', 'ASC');
            }
        }

        if (isset($street_number)) {
            $property = $property->where('street_number', $street_number);
        }
        if (isset($street_code)) {
            $property = $property->where('street_code', $street_code);
        }
        if (isset($ward_code)) {
            $property = $property->where('ward_code', $ward_code);
        }
        if (isset($host_id)) {
            $property = $property->where('host_id', $host_id);
        }

        if (isset($parameter_id)) {

            $property = $property->whereHas('parameters', function ($q) use ($parameter_id) {
                $q->where('parameter_id', $parameter_id);
            });
        }
        if (isset($userid)) {
            $property = $property->where('post_type', 1)->where('added_by', $userid);
        } else {
            $property = $property->Where('status', 1);
        }


        if (isset($max_price) && isset($min_price)) {
            $property = $property->whereBetween('price', [$min_price, $max_price]);
        }
        if (isset($property_type)) {
            if ($property_type == 0 || $property_type == 2) {
                $property = $property->where('propery_type', $property_type);
            }
            if ($property_type == 1 || $property_type == 3) {
                $property = $property->where('propery_type', $property_type);
            }
        }

        if (isset($posted_since)) {
            // 0: last_week   1: yesterday
            if ($posted_since == 0) {
                $property = $property->whereBetween(
                    'created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                );
            }
            if ($posted_since == 1) {
                $property = $property->whereDate('created_at', Carbon::yesterday());
            }
        }

        if (isset($category_id)) {
            $property = $property->where('category_id', $category_id);
        }
        if (isset($id)) {
            $property = $property->where('id', $id);
        }
        if (isset($country)) {
            $property = $property->where('country', $country);
        }
        if (isset($state)) {
            $property = $property->where('state', $state);
        }
        if (isset($city) && $city != '') {
            $property = $property->where('city', $city);
        }

        if (isset($furnished)) {
            $property = $property->where('furnished', $furnished);
        }
        if (isset($request->promoted)) {
            $adv = Advertisement::select('property_id')->where('is_enable', 1)->get();

            $ad_arr = [];
            foreach ($adv as $ad) {

                array_push($ad_arr, $ad->property_id);
            }

            $property = $property->whereIn('id', $ad_arr);
        } else {

            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        if (isset($request->users_promoted)) {
            $adv = Advertisement::select('property_id')->where('customer_id', $current_user)->where('is_enable', 1)->get();

            $ad_arr = [];
            foreach ($adv as $ad) {

                array_push($ad_arr, $ad->property_id);
            }
            $property = $property->whereIn('id', $ad_arr);
        } else {

            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        if (isset($request->promoted)) {



            if (!($property->Has('advertisement'))) {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
                return ($response);
            }

            $property = $property->with('advertisement');
        }

        if (isset($request->search) && !empty($request->search)) {
            $search = $request->search;

            $property = $property->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%$search%")->orwhere('address', 'LIKE', "%$search%")->orwhereHas('category', function ($query1) use ($search) {
                    $query1->where('category', 'LIKE', "%$search%");
                });
            });
        }
        if (empty($request->search)) {
            $property = $property;
        }



        if (isset($top_rated) && $top_rated == 1) {

            $property = $property->orderBy('total_click', 'DESC');
        }

        if (!$request->most_liked && !$request->top_rated) {
            $property = $property->orderBy('id', 'DESC');
        }
        if ($request->most_liked) {

            $property = $property->withCount('favourite')
                ->orderBy('favourite_count', 'DESC');
        }
        $total = $property->get()->count();

        $result = $property->skip($offset)->take($limit)->get();

        //dd(DB::getQueryLog());


        if (!$result->isEmpty()) {
            $property_details
                = get_property_details($result, $current_user);


            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['total'] = $total;
            $response['data'] = $property_details;
        } else {

            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return ($response);
    }
    //* END :: get_property   *//
    //* START :: post_property   *//
    public function post_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // HuyTBQ: Disable packeage modules
            //'package_id' => 'required',
            //'title_image' => 'required|file|max:3000|mimes:jpeg,png,jpg',
        ]);

        if (!$validator->fails()) {
            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);


            $package = UserPurchasedPackage::where('modal_id', $current_user)->with([
                'package' => function ($q) {
                    $q->select('id', 'property_limit', 'advertisement_limit')->where('property_limit', '!=', NULL);
                }
            ])->first();


            $arr = 0;

            $prop_count = 0;
            if (!($package)) {
                $response['error'] = false;
                $response['message'] = 'Package not found';
                return response()->json($response);
            } else {

                if (!$package->package) {

                    $response['error'] = false;
                    $response['message'] = 'Package not found for add property';
                    return response()->json($response);
                }
                $prop_count = $package->package->property_limit;

                $arr = $package->id;



                $propeerty_limit = Property::where('added_by', $current_user)->where('package_id', $request->package_id)->get();


                if (($package->used_limit_for_property) < ($prop_count) || $prop_count == 0) {

                    $validator = Validator::make($request->all(), [
                        'userid' => 'required',
                        'category_id' => 'required',

                    ]);



                    $destinationPath = public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    /// START :: HuyTBQ: Add host module
                    // Extract host information from request
                    $hostName = $request->host_name;
                    $hostGender = $request->host_gender;
                    $hostContact = $request->host_contact;
                    $hostAbout = $request->host_about;

                    // Check if a CRM Host with the provided contact exists
                    $crmHost = CrmHost::create([
                        'name' => $hostName,
                        'gender' => $hostGender,
                        'contact' => $hostContact,
                        'about' => $hostAbout,
                        // Thêm các trường khác nếu cần
                    ]);
                    $crmHost->save();
                    /// END :: HuyTBQ: Add host module

                    $Saveproperty = new Property();
                    $Saveproperty->category_id = $request->category_id;

                    $Saveproperty->title = $request->title;
                    $Saveproperty->description = $request->description;
                    $Saveproperty->address = $request->address;
                    $Saveproperty->client_address = (isset($request->client_address)) ? $request->client_address : '';

                    $Saveproperty->propery_type = (isset($request->property_type)) ? $request->property_type : 0;
                    $Saveproperty->price = (isset($request->price)) ? $request->price :0 ;

                    $Saveproperty->country = (isset($request->country)) ? $request->country : '';
                    $Saveproperty->state = (isset($request->state)) ? $request->state : '';
                    $Saveproperty->city = (isset($request->city)) ? $request->city : '';
                    $Saveproperty->latitude = (isset($request->latitude)) ? $request->latitude : '';
                    $Saveproperty->longitude = (isset($request->longitude)) ? $request->longitude : '';
                    $Saveproperty->rentduration = (isset($request->rentduration)) ? $request->rentduration : '';


                    //HuyTBQ: add address columns for properites table
                    $Saveproperty->street_code = (isset($request->street_code)) ? $request->street_code : '';
                    $Saveproperty->ward_code = (isset($request->ward_code)) ? $request->ward_code : '';
                    $Saveproperty->street_number =  (isset($request->street_number)) ? $request->street_number : '';
                    //HuyTBQ: add commission columns for properites table
                    $Saveproperty->commission = (isset($request->commission)) ? $request->commission :0 ;

                    $Saveproperty->added_by = $current_user;
                    $Saveproperty->status = (isset($request->status)) ? $request->status : 0;
                    $Saveproperty->video_link = (isset($request->video_link)) ? $request->video_link : "";

                    $Saveproperty->package_id = $request->package_id;

                    $Saveproperty->post_type = 1;
                    if ($request->hasFile('title_image')) {
                        $profile = $request->file('title_image');
                        $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                        $profile->move($destinationPath, $imageName);
                        $Saveproperty->title_image = $imageName;
                    } else {
                        $Saveproperty->title_image = '';
                    }

                    // threeD_image
                    if ($request->hasFile('threeD_image')) {
                        $destinationPath = public_path('images') . config('global.3D_IMG_PATH');
                        if (!is_dir($destinationPath)) {
                            mkdir($destinationPath, 0777, true);
                        }
                        // $Saveproperty->threeD_image_hash = get_hash($request->file('threeD_image'));
                        $profile = $request->file('threeD_image');
                        $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                        $profile->move($destinationPath, $imageName);
                        $Saveproperty->threeD_image = $imageName;
                    } else {
                        $Saveproperty->threeD_image = '';
                    }


                    //HuyTBQ: Assign CRM Host ID to the property
                    $Saveproperty->host_id = $crmHost->id;

                    //print_r(json_encode($request->parameters));
                    $Saveproperty->save();
                    $package->used_limit_for_property
                        = $package->used_limit_for_property + 1;
                    $package->save();
                    $destinationPathforparam = public_path('images') . config('global.PARAMETER_IMAGE_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    if ($request->facilities) {
                        foreach ($request->facilities as $key => $value) {

                            $facilities = new AssignedOutdoorFacilities();
                            $facilities->facility_id = $value['facility_id'];
                            $facilities->property_id = $Saveproperty->id;
                            $facilities->distance = $value['distance'];
                            $facilities->save();
                        }
                    }
                    if ($request->parameters) {
                        foreach ($request->parameters as $key => $parameter) {

                            // dd($parameter['value']);
                            $AssignParameters = new AssignParameters();

                            $AssignParameters->modal()->associate($Saveproperty);

                            $AssignParameters->parameter_id = $parameter['parameter_id'];
                            if ($request->hasFile('parameters.' . $key . '.value')) {

                                $profile = $request->file('parameters.' . $key . '.value');
                                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                                $profile->move($destinationPathforparam, $imageName);
                                $AssignParameters->value = $imageName;
                            } else if (filter_var($parameter['value'], FILTER_VALIDATE_URL)) {


                                $ch = curl_init($parameter['value']);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $fileContents = curl_exec($ch);
                                curl_close($ch);

                                $filename
                                    = microtime(true) . basename($parameter['value']);

                                file_put_contents($destinationPathforparam . '/' . $filename, $fileContents);
                                $AssignParameters->value = $filename;
                            } else {
                                $AssignParameters->value = $parameter['value'];
                            }

                            $AssignParameters->save();
                        }
                    }

                    /// START :: UPLOAD GALLERY IMAGE

                    $FolderPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH');
                    if (!is_dir($FolderPath)) {
                        mkdir($FolderPath, 0777, true);
                    }


                    $destinationPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . "/" . $Saveproperty->id;
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    if ($request->hasfile('gallery_images')) {


                        foreach ($request->file('gallery_images') as $file) {


                            $name = time() . rand(1, 100) . '.' . $file->extension();
                            $file->move($destinationPath, $name);

                            $gallary_image = new PropertyImages();
                            $gallary_image->image = $name;
                            $gallary_image->propertys_id = $Saveproperty->id;

                            $gallary_image->save();
                        }
                    }

                    if ($request->hasfile('legal_images')) {


                        foreach ($request->file('legal_images') as $file) {
                            $name = time() . rand(1, 100) . '.' . $file->extension();
                            $file->move($destinationPath, $name);

                            $gallary_legal_image = new PropertyLegalImage();
                            $gallary_legal_image->image = $name;
                            $gallary_legal_image->propertys_id = $Saveproperty->id;

                            $gallary_legal_image->save();
                        }
                    }

                    /// END :: UPLOAD GALLERY IMAGE

                    $result = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $Saveproperty->id)->get();
                    $property_details = get_property_details($result);

                    $response['error'] = false;
                    $response['message'] = 'Property Post Succssfully';
                    $response['data'] = $property_details;
                } else {
                    $response['error'] = false;
                    $response['message'] = 'Package Limit is over';
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    //* END :: post_property   *//
    //* START :: update_post_property   *//
    /// This api use for update and delete  property
    public function update_post_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'action_type' => 'required'
        ]);
        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);
        if (!$validator->fails()) {
            $id = $request->id;
            $action_type = $request->action_type;

            $property = Property::where('added_by', $current_user)->find($id);
            if (($property)) {
                // 0: Update 1: Delete
                if ($action_type == 0) {

                    $destinationPath = public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }

                    if (isset($request->category_id)) {
                        $property->category_id = $request->category_id;
                    }

                    if (isset($request->title)) {
                        $property->title = $request->title;
                    }

                    if (isset($request->description)) {
                        $property->description = $request->description;
                    }

                    if (isset($request->address)) {
                        $property->address = $request->address;
                    }

                    if (isset($request->client_address)) {
                        $property->client_address = $request->client_address;
                    }

                    if (isset($request->propery_type)) {
                        $property->propery_type = $request->propery_type;
                    }
                    if (isset($request->commission)) {
                        $property->commission = $request->commission;
                    }

                    if (isset($request->price)) {
                        $property->price = $request->price;
                    }
                    if (isset($request->country)) {
                        $property->country = $request->country;
                    }
                    if (isset($request->state)) {
                        $property->state = $request->state;
                    }
                    if (isset($request->city)) {
                        $property->city = $request->city;
                    }
                    if (isset($request->status)) {
                        $property->status = $request->status;
                    }
                    if (isset($request->latitude)) {
                        $property->latitude = $request->latitude;
                    }
                    if (isset($request->longitude)) {
                        $property->longitude = $request->longitude;
                    }
                    if (isset($request->rentduration)) {
                        $property->rentduration = $request->rentduration;
                    }
                    if ($request->hasFile('title_image')) {
                        $profile = $request->file('title_image');
                        $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                        $profile->move($destinationPath, $imageName);


                        if ($property->title_image != '') {
                            if (file_exists(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') . $property->title_image)) {
                                unlink(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') . $property->title_image);
                            }
                        }
                        $property->title_image = $imageName;
                    }
                    if ($request->hasFile('threeD_image')) {
                        $destinationPath1 = public_path('images') . config('global.3D_IMG_PATH');
                        if (!is_dir($destinationPath1)) {
                            mkdir($destinationPath1, 0777, true);
                        }
                        $profile = $request->file('threeD_image');
                        $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                        $profile->move($destinationPath1, $imageName);


                        if ($property->title_image != '') {
                            if (file_exists(public_path('images') . config('global.3D_IMG_PATH') . $property->title_image)) {
                                unlink(public_path('images') . config('global.3D_IMG_PATH') . $property->title_image);
                            }
                        }
                        $property->threeD_image = $imageName;
                    }
                    if ($request->parameters) {
                        $destinationPathforparam = public_path('images') . config('global.PARAMETER_IMAGE_PATH');
                        if (!is_dir($destinationPath)) {
                            mkdir($destinationPath, 0777, true);
                        }
                        // dd($request->parameters);

                        foreach ($request->parameters as $key => $parameter) {
                            // print_r($parameter);
                            // echo $property->id;
                            // return false;
                            $AssignParameters = AssignParameters::where('modal_id', $property->id)->where('parameter_id', $parameter['parameter_id'])->pluck('id');
                            // echo $AssignParameters[0] . 'idddd';
                            // dd($parameter['parameter_id']);

                            if (count($AssignParameters)) {
                                $update_data = AssignParameters::find($AssignParameters[0]);

                                if ($request->hasFile('parameters.' . $key . '.value')) {

                                    $profile = $request->file('parameters.' . $key . '.value');
                                    $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                                    $profile->move($destinationPathforparam, $imageName);
                                    $update_data->value = $imageName;
                                } else if (filter_var($parameter['value'], FILTER_VALIDATE_URL)) {



                                    $ch = curl_init($parameter['value']);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    $fileContents = curl_exec($ch);
                                    curl_close($ch);


                                    $filename
                                        = microtime(true) . basename($parameter['value']);
                                    // dd($filename);
                                    file_put_contents($destinationPathforparam . '/' . $filename, $fileContents);
                                    $update_data->value = $filename;
                                } else {
                                    $update_data->value = $parameter['value'];
                                }


                                $update_data->save();
                            } else {

                                $AssignParameters = new AssignParameters();

                                $AssignParameters->modal()->associate($property);

                                $AssignParameters->parameter_id = $parameter['parameter_id'];
                                if ($request->hasFile('parameters.' . $key . '.value')) {

                                    $profile = $request->file('parameters.' . $key . '.value');
                                    $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                                    $profile->move($destinationPathforparam, $imageName);
                                    $AssignParameters->value = $imageName;
                                } else if (filter_var($parameter['value'], FILTER_VALIDATE_URL)) {


                                    $ch = curl_init($parameter['value']);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    $fileContents = curl_exec($ch);
                                    curl_close($ch);

                                    $filename
                                        = microtime(true) . basename($parameter['value']);

                                    file_put_contents($destinationPathforparam . '/' . $filename, $fileContents);
                                    $AssignParameters->value = $filename;
                                } else {
                                    $AssignParameters->value = $parameter['value'];
                                }

                                $AssignParameters->save();
                            }
                        }

                        // $AssignParameters->save();
                    }
                    AssignedOutdoorFacilities::where('property_id', $request->id)->delete();
                    if ($request->facilities) {
                        foreach ($request->facilities as $key => $value) {


                            $facilities = new AssignedOutdoorFacilities();
                            $facilities->facility_id = $value['facility_id'];
                            $facilities->property_id = $request->id;
                            $facilities->distance = $value['distance'];
                            $facilities->save();
                        }
                    }
                    /// START :: HuyTBQ : Update property type
                    if (isset($request->property_type)) {
                        if ($request->property_type == "Sell") {
                            $property->propery_type = 0;
                        } elseif ($request->property_type == "Rent") {
                            $property->propery_type = 1;
                        } elseif ($request->property_type == "Sold") {
                            $property->propery_type = 2;
                        } elseif ($request->property_type == "Rented") {
                            $property->propery_type = 3;
                        }
                    }
                    //HuyTBQ: test
                    //$property->propery_type = 1;
                    /// END :: HuyTBQ : Update property type

                    /// START :: HuyTBQ: Add host module
                    // Extract host information from request
                    $hostName = $request->host_name;
                    $hostGender = $request->host_gender;
                    $hostContact = $request->host_contact;
                    $hostAbout = $request->host_about;

                    // Check if a CRM Host with the provided contact exists
                    $crmHost = CrmHost::updateOrCreate(
                        ['id' => $property->host_id], // Điều kiện tìm kiếm
                        [
                            'name' => $hostName,
                            'gender' => $hostGender,
                            'contact' => $hostContact,
                            'about' => $hostAbout,
                            // Thêm các trường khác nếu cần
                        ]
                    );
                    $crmHost->save();
                    //HuyTBQ: Assign CRM Host ID to the property
                    $property->host_id = $crmHost->id;

                    /// END :: HuyTBQ: Add host module

                    /// START :: HuyTBQ : Update location module
                    $property->street_code = (isset($request->street_code)) ? $request->street_code : '';
                    $property->ward_code = (isset($request->ward_code)) ? $request->ward_code : '';
                    $property->street_number =  (isset($request->street_number)) ? $request->street_number : '';
                    /// END :: HuyTBQ : Update location module

                    $property->update();
                    $update_property = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $request->id)->get();

                    /// START :: UPLOAD GALLERY IMAGE
                    $FolderPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH');
                    if (!is_dir($FolderPath)) {
                        mkdir($FolderPath, 0777, true);
                    }

                    $destinationPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . "/" . $property->id;
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }

                    //gallery images
                    if ($request->remove_gallery_images) {
                        foreach ($request->remove_gallery_images as $key => $value) {
                            $gallary_images = PropertyImages::find($value);
                            if (file_exists(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $gallary_images->propertys_id . '/' . $gallary_images->image)) {

                                unlink(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $gallary_images->propertys_id . '/' . $gallary_images->image);
                            }
                            $gallary_images->delete();
                        }
                    }
                    if ($request->hasfile('gallery_images')) {
                        foreach ($request->file('gallery_images') as $file) {
                            $name = time() . rand(1, 100) . '.' . $file->extension();
                            $file->move($destinationPath, $name);
                            PropertyImages::create([
                                'image' => $name,
                                'propertys_id' => $property->id,
                            ]);
                        }
                    }

                    //HuyTBQ: add for update properties
                    // legal images
                    if ($request->remove_legal_images) {
                        foreach ($request->remove_legal_images as $key => $value) {
                            $legal_images = PropertyLegalImage::find($value);
                            if (file_exists(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $legal_images->propertys_id . '/' . $legal_images->image)) {
                                unlink(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $legal_images->propertys_id . '/' . $legal_images->image);
                            }
                            $legal_images->delete();
                        }
                    }
                    if ($request->hasfile('legal_images')) {
                        foreach ($request->file('legal_images') as $file) {
                            $name = time() . rand(1, 100) . '.' . $file->extension();
                            $file->move($destinationPath, $name);
                            PropertyLegalImage::create([
                                'image' => $name,
                                'propertys_id' => $property->id,
                            ]);
                        }
                    }

                    /// END :: UPLOAD GALLERY IMAGE
                    $payload = JWTAuth::getPayload($this->bearerToken($request));
                    $current_user = ($payload['customer_id']);
                    $property_details = get_property_details($update_property, $current_user);
                    $response['error'] = false;
                    $response['message'] = 'Property Update Successfully';
                    $response['data'] = $property_details;
                } elseif ($action_type == 1) {
                    if ($property->delete()) {

                        $chat = Chats::where('property_id', $property->id);
                        if ($chat) {
                            $chat->delete();
                        }

                        $enquiry = PropertysInquiry::where('propertys_id', $property->id);
                        if ($enquiry) {
                            $enquiry->delete();
                        }

                        $slider = Slider::where('propertys_id', $property->id);
                        if ($slider) {
                            $slider->delete();
                        }


                        $notifications = Notifications::where('propertys_id', $property->id);
                        if ($notifications) {
                            $notifications->delete();
                        }

                        if ($property->title_image != '') {
                            if (file_exists(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') . $property->title_image)) {
                                unlink(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') . $property->title_image);
                            }
                        }
                        foreach ($property->gallery as $row) {
                            if (PropertyImages::where('id', $row->id)->delete()) {
                                if ($row->image_url != '') {
                                    if (file_exists(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $property->id . "/" . $row->image)) {
                                        unlink(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $property->id . "/" . $row->image);
                                    }
                                }
                            }
                        }
                        rmdir(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $property->id);

                        Notifications::where('propertys_id', $id)->delete();


                        $slider = Slider::where('propertys_id', $id)->get();

                        foreach ($slider as $row) {
                            $image = $row->image;

                            if (Slider::where('id', $row->id)->delete()) {
                                if (file_exists(public_path('images') . config('global.SLIDER_IMG_PATH') . $image)) {
                                    unlink(public_path('images') . config('global.SLIDER_IMG_PATH') . $image);
                                }
                            }
                        }

                        $response['error'] = false;
                        $response['message'] = 'Delete Successfully';
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'something wrong';
                    }
                }
            } else {
                $response['error'] = true;
                $response['message'] = 'No Data Found';
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: update_post_property   *//
    //* START :: remove_post_images   *//
    public function remove_post_images(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if (!$validator->fails()) {
            $id = $request->id;
            $getImage = PropertyImages::where('id', $id)->first();
            $image = $getImage->image;
            $propertys_id = $getImage->propertys_id;

            if (PropertyImages::where('id', $id)->delete()) {
                if (file_exists(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id . "/" . $image)) {
                    unlink(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id . "/" . $image);
                }
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }

            $countImage = PropertyImages::where('propertys_id', $propertys_id)->get();
            if ($countImage->count() == 0) {
                rmdir(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id);
            }

            $response['error'] = false;
            $response['message'] = 'Property Post Succssfully';
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: remove_post_images   *//
    //* START :: set_property_inquiry   *//
    public function set_property_inquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action_type' => 'required',
        ]);

        if (!$validator->fails()) {
            $action_type = $request->action_type; ////0: add   1:update
            if ($action_type == 0) {
                //add inquiry
                $validator = Validator::make($request->all(), [
                    'property_id' => 'required',
                ]);
                $payload = JWTAuth::getPayload($this->bearerToken($request));
                $current_user = ($payload['customer_id']);
                if (!$validator->fails()) {
                    $PropertysInquiry = PropertysInquiry::where('propertys_id', $request->property_id)->where('customers_id', $current_user)->first();
                    if (empty($PropertysInquiry)) {
                        PropertysInquiry::create([
                            'propertys_id' => $request->property_id,
                            'customers_id' => $current_user,
                            'status' => '0'
                        ]);
                        $response['error'] = false;
                        $response['message'] = 'Inquiry Send Succssfully';
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'Request Already Submitted';
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = "Please fill all data and Submit";
                }
            } elseif ($action_type == 1) {
                //update inquiry
                $validator = Validator::make($request->all(), [
                    'id' => 'required',
                    'status' => 'required',
                ]);

                if (!$validator->fails()) {
                    $id = $request->id;
                    $propertyInquiry = PropertysInquiry::find($id);
                    $propertyInquiry->status = $request->status;
                    $propertyInquiry->update();

                    $response['error'] = false;
                    $response['message'] = 'Inquiry Update Succssfully';
                } else {
                    $response['error'] = true;
                    $response['message'] = "Please fill all data and Submit";
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    //* END :: set_property_inquiry   *//
    //* START :: get_notification_list   *//
    public function get_notification_list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required',
        ]);

        if (!$validator->fails()) {
            $id = $request->userid;

            $Notifications = Notifications::whereRaw("FIND_IN_SET($id,customers_id)")->orwhere('send_type', '1')->orderBy('id', 'DESC')->get();


            if (!$Notifications->isEmpty()) {
                for ($i = 0; $i < count($Notifications); $i++) {
                    $Notifications[$i]->created = $Notifications[$i]->created_at->diffForHumans();
                    $Notifications[$i]->image = ($Notifications[$i]->image != '') ? url('') . config('global.IMG_PATH') . config('global.NOTIFICATION_IMG_PATH') . $Notifications[$i]->image : '';
                }
                $response['error'] = false;
                $response['data'] = $Notifications;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: get_notification_list   *//
    //* START :: get_property_inquiry   *//
    public function get_property_inquiry(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);
        $propertyInquiry = PropertysInquiry::with('property')->where('customers_id', $current_user);
        $total = $propertyInquiry->get()->count();
        $result = $propertyInquiry->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        $rows = array();
        $tempRow = array();
        $count = 1;

        if (!$result->isEmpty()) {

            foreach ($result as $key => $row) {
                // print_r($row->toArray());
                $tempRow['id'] = $row->id;
                $tempRow['propertys_id'] = $row->propertys_id;
                $tempRow['customers_id'] = $row->customers_id;
                $tempRow['status'] = $row->status;
                $tempRow['created_at'] = $row->created_at;
                $tempRow['property']['id'] = $row['property']->id;
                $tempRow['property']['title'] = $row['property']->title;
                $tempRow['property']['price'] = $row['property']->price;
                $tempRow['property']['category'] = $row['property']->category;
                $tempRow['property']['description'] = $row['property']->description;
                $tempRow['property']['address'] = $row['property']->address;
                $tempRow['property']['client_address'] = $row['property']->client_address;
                $tempRow['property']['propery_type'] = ($row['property']->propery_type == '0') ? 'Sell' : 'Rent';
                $tempRow['property']['title_image'] = $row['property']->title_image;
                $tempRow['property']['threeD_image'] = $row['property']->threeD_image;
                $tempRow['property']['post_created'] = $row['property']->created_at->diffForHumans();
                $tempRow['property']['gallery'] = $row['property']->gallery;
                $tempRow['property']['total_view'] = $row['property']->total_click;
                $tempRow['property']['status'] = $row['property']->status;
                $tempRow['property']['state'] = $row['property']->state;
                $tempRow['property']['city'] = $row['property']->city;
                $tempRow['property']['country'] = $row['property']->country;
                $tempRow['property']['latitude'] = $row['property']->latitude;
                $tempRow['property']['longitude'] = $row['property']->longitude;
                $tempRow['property']['added_by'] = $row['property']->added_by;
                foreach ($row->property->assignParameter as $key => $res) {


                    $tempRow['property']["parameters"][$key] = $res->parameter;

                    $tempRow['property']["parameters"][$key]["value"] = $res->value;
                }


                $rows[] = $tempRow;
                // $parameters[] = $arr;
                $count++;
            }

            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['total'] = $total;
            $response['data'] = $rows;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }


        return response()->json($response);
    }
    //* END :: get_property_inquiry   *//
    //* START :: set_property_total_click   *//
    public function set_property_total_click(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
        ]);

        if (!$validator->fails()) {
            $property_id = $request->property_id;


            $Property = Property::find($property_id);
            $Property->increment('total_click');

            $response['error'] = false;
            $response['message'] = 'Update Succssfully';
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: set_property_total_click   *//
    //* START :: delete_user   *//
    public function delete_user(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required',

        ]);

        if (!$validator->fails()) {
            $userid = $request->userid;

            Customer::find($userid)->delete();
            Property::where('added_by', $userid)->delete();
            PropertysInquiry::where('customers_id', $userid)->delete();

            $response['error'] = false;
            $response['message'] = 'Delete Succssfully';
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: delete_user   *//
    public function bearerToken($request)
    {
        $header = $request->header('Authorization', '');
        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }
    }
    //*START :: add favoutite *//
    public function add_favourite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'property_id' => 'required',


        ]);

        if (!$validator->fails()) {
            //add favourite
            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);
            if ($request->type == 1) {


                $fav_prop = Favourite::where('user_id', $current_user)->where('property_id', $request->property_id)->get();

                if (count($fav_prop) > 0) {
                    $response['error'] = false;
                    $response['message'] = "Property already add to favourite";
                    return response()->json($response);
                }
                $favourite = new Favourite();
                $favourite->user_id = $current_user;
                $favourite->property_id = $request->property_id;
                $favourite->save();
                $response['error'] = false;
                $response['message'] = "Property add to Favourite add successfully";
            }
            //delete favourite
            if ($request->type == 0) {
                Favourite::where('property_id', $request->property_id)->where('user_id', $current_user)->delete();

                $response['error'] = false;
                $response['message'] = "Property remove from Favourite  successfully";
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }


        return response()->json($response);
    }

    public function get_articles(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $article = Article::select('id', 'image', 'title', 'description', 'created_at');

        $total = $article->get()->count();
        $result = $article->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        if (!$result->isEmpty()) {
            foreach ($article as $row) {

                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {

                    $row->image = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.ARTICLE_IMG_PATH') . $row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function store_advertisement(Request $request)
    {
        // dd($request->toArray());
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'property_id' => 'required',
            'package_id' => 'required',
        ]);
        if (!$validator->fails()) {
            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);

            $userpackage = UserPurchasedPackage::where('modal_id', $current_user)->with([
                'package' => function ($q) {
                    $q->select('id', 'property_limit', 'advertisement_limit')->where('advertisement_limit', '!=', NULL);
                }
            ])->first();
            $arr = 0;

            $prop_count = 0;
            if (!($userpackage)) {
                $response['error'] = false;
                $response['message'] = 'Package not found';
                return response()->json($response);
            } else {

                if (!$userpackage->package) {

                    $response['error'] = false;
                    $response['message'] = 'Package not found for add property';
                    return response()->json($response);
                }
                $advertisement_count = $userpackage->package->advertisement_limit;

                $arr = $userpackage->id;

                $advertisement_limit = Advertisement::where('customer_id', $current_user)->where('package_id', $request->package_id)->get();


                if ($userpackage->used_limit_for_advertisement < ($advertisement_count) || $advertisement_count == 0) {

                    $payload = JWTAuth::getPayload($this->bearerToken($request));
                    $current_user = ($payload['customer_id']);

                    $package = Package::where('advertisement_limit', '!=', NULL)->find($request->package_id);

                    $adv = new Advertisement();

                    $adv->start_date = Carbon::now();
                    if ($package) {
                        if (isset($request->end_date)) {
                            $adv->end_date = $request->end_date;
                        } else {
                            $adv->end_date = Carbon::now()->addDays($package->duration);
                        }
                        $adv->package_id = $package->id;
                        $adv->type = $request->type;
                        $adv->property_id = $request->property_id;
                        $adv->customer_id = $current_user;
                        $adv->is_enable = false;
                        $adv->status = 0;

                        $destinationPath = public_path('images') . config('global.ADVERTISEMENT_IMAGE_PATH');
                        if (!is_dir($destinationPath)) {
                            mkdir($destinationPath, 0777, true);
                        }

                        if ($request->type == 'Slider') {
                            $destinationPath_slider = public_path('images') . config('global.SLIDER_IMG_PATH');

                            if (!is_dir($destinationPath_slider)) {
                                mkdir($destinationPath_slider, 0777, true);
                            }
                            $slider = new Slider();

                            if ($request->hasFile('image')) {


                                $file = $request->file('image');


                                $name = time() . rand(1, 100) . '.' . $file->extension();

                                $file->move($destinationPath_slider, $name);
                                $sliderimageName = microtime(true) . "." . $file->getClientOriginalExtension();
                                $slider->image = $sliderimageName;
                            } else {
                                $slider->image = '';
                            }
                            $slider->category_id = isset($request->category_id) ? $request->category_id : 0;
                            $slider->propertys_id = $request->property_id;
                            $slider->save();
                        }
                        $result = Property::with('customer')->with('category:id,category,image')->with('favourite')->with('parameters')->with('interested_users')->where('id', $request->property_id)->get();
                        $property_details = get_property_details($result);

                        $adv->image = "";
                        $adv->save();
                        $userpackage->used_limit_for_advertisement = $userpackage->used_limit_for_advertisement + 1;

                        $userpackage->save();
                        $response['error'] = false;
                        $response['message'] = "Advertisement add successfully";
                        $response['data'] = $property_details;
                    } else {
                        $response['error'] = false;
                        $response['message'] = "Package not found";
                        return response()->json($response);
                    }
                } else {
                    $response['error'] = false;
                    $response['message'] = "Package Limit is over";
                    return response()->json($response);
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function get_advertisement(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $article = Article::select('id', 'image', 'title', 'description');
        $date = date('Y-m-d');
        DB::enableQueryLog();
        $adv = Advertisement::select('id', 'image', 'category_id', 'property_id', 'type', 'customer_id', 'is_enable', 'status')->with('customer:id,name')->where('end_date', '>', $date);
        if (isset($request->customer_id)) {
            $adv->where('customer_id', $request->customer_id);
        }
        $total = $adv->get()->count();
        $result = $adv->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        if (!$result->isEmpty()) {
            foreach ($adv as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.ADVERTISEMENT_IMAGE_PATH') . $row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }


        return response()->json($response);
    }
    public function get_package(Request $request)
    {

        $date = date('Y-m-d');
        DB::enableQueryLog();
        $package = Package::where('status', 1)->orderBy('id', 'ASC')->where('name', '!=', 'Trial Package')->get();
        // dd(DB::getQueryLog());
        if (!$package->isEmpty()) {

            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $package;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }

        return response()->json($response);
    }
    public function user_purchase_package(Request $request)
    {

        $start_date = Carbon::now();
        $validator = Validator::make($request->all(), [

            'package_id' => 'required',

        ]);

        if (!$validator->fails()) {
            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);
            if (isset($request->flag)) {
                $user_exists = UserPurchasedPackage::where('modal_id', $current_user)->get();
                if ($user_exists) {
                    UserPurchasedPackage::where('modal_id', $current_user)->delete();
                }
            }

            $package = Package::find($request->package_id);
            $user = Customer::find($current_user);
            $data_exists = UserPurchasedPackage::where('modal_id', $current_user)->get();
            if (count($data_exists) == 0 && $package) {
                $user_package = new UserPurchasedPackage();
                $user_package->modal()->associate($user);
                $user_package->package_id = $request->package_id;
                $user_package->start_date = $start_date;
                $user_package->end_date = $package->duratio != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                $user_package->save();

                $user->subscription = 1;
                $user->update();

                $response['error'] = false;
                $response['message'] = "purchased package  add successfully";
            } else {
                $response['error'] = false;
                $response['message'] = "data already exists or package not found or add flag for add new package";
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function get_favourite_property(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;


        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);
        DB::enableQueryLog(); // Enable query log


        $favourite = Favourite::where('user_id', $current_user)->select('property_id')->get();
        // dd($favourite);
        $arr = array();
        foreach ($favourite as $p) {
            $arr[] = $p->property_id;
        }

        $property_details = Property::whereIn('id', $arr)->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('parameters');
        $result = $property_details->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();

        $total = $result->count();

        if (!$result->isEmpty()) {
            $result->transform(function ($property) {
                if ($property->propery_type == 0) {
                    $property->propery_type = "Sell";
                } elseif ($property->propery_type == 1) {
                    $property->propery_type = "Rent";
                } elseif ($property->propery_type == 2) {
                    $property->propery_type = "Sold";
                } elseif ($property->propery_type == 3) {
                    $property->propery_type = "Rented";
                }
                return $property;
            });
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = get_property_details($result);
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function delete_advertisement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',

        ]);

        if (!$validator->fails()) {
            $adv = Advertisement::find($request->id);
            if (!$adv) {
                $response['error'] = false;
                $response['message'] = "Data not found";
            } else {

                $adv->delete();
                $response['error'] = false;
                $response['message'] = "Advertisement Deleted successfully";
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function interested_users(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
            'type' => 'required'


        ]);
        if (!$validator->fails()) {

            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);

            $interested_user = InterestedUser::where('customer_id', $current_user)->where('property_id', $request->property_id);

            if ($request->type == 1) {

                if (count($interested_user->get()) > 0) {
                    $response['error'] = false;
                    $response['message'] = "already added to interested users ";
                } else {
                    $interested_user = new InterestedUser();
                    $interested_user->property_id = $request->property_id;
                    $interested_user->customer_id = $current_user;
                    $interested_user->save();
                    $response['error'] = false;
                    $response['message'] = "Interested Users added successfully";
                }
            }
            if ($request->type == 0) {

                if (count($interested_user->get()) == 0) {
                    $response['error'] = false;
                    $response['message'] = "No data found to delete";
                } else {
                    $interested_user->delete();

                    $response['error'] = false;
                    $response['message'] = "Interested Users removed  successfully";
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function delete_inquiry(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required',

        ]);

        if (!$validator->fails()) {
            $adv = PropertysInquiry::where('status', 0)->find($request->id);
            if (!$adv) {
                $response['error'] = false;
                $response['message'] = "Data not found";
            } else {

                $adv->delete();
                $response['error'] = false;
                $response['message'] = "Property inquiry Deleted successfully";
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function user_interested_property(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;


        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);
        DB::enableQueryLog(); // Enable query log


        $favourite = InterestedUser::where('customer_id', $current_user)->select('property_id')->get();
        // dd($favourite);
        $arr = array();
        foreach ($favourite as $p) {
            $arr[] = $p->property_id;
        }
        $property_details = Property::whereIn('id', $arr)->with('category:id,category')->with('parameters');
        $result = $property_details->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        // dd(\DB::getQueryLog());

        $total = $result->count();

        if (!$result->isEmpty()) {
            foreach ($property_details as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.PROPERTY_TITLE_IMG_PATH') . $row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $result;
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_limits(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'id' => 'required',
        ]);
        if (!$validator->fails()) {
            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);
            $package = UserPurchasedPackage::where('modal_id', $current_user)->where('package_id', $request->id)->with([
                'package' => function ($q) {
                    $q->select('id', 'property_limit', 'advertisement_limit');
                }
            ])->first();
            if (!$package) {
                $response['error'] = true;
                $response['message'] = "package not found";
                return response()->json($response);
            }
            $arr = 0;
            $adv_count = 0;
            $prop_count = 0;
            // foreach ($package as $p) {

            ($adv_count = $package->package->advertisement_limit == 0 ? "Unlimited" : $package->package->advertisement_limit);
            ($prop_count = $package->package->property_limit == 0 ? "Unlimited" : $package->package->property_limit);

            ($arr = $package->id);
            // }

            $advertisement_limit = Advertisement::where('customer_id', $current_user)->where('package_id', $request->id)->get();
            // DB::enableQueryLog();

            $propeerty_limit = Property::where('added_by', $current_user)->where('package_id', $request->id)->get();


            $response['total_limit_of_advertisement'] = ($adv_count);
            $response['total_limit_of_property'] = ($prop_count);


            $response['used_limit_of_advertisement'] = $package->used_limit_for_advertisement;
            $response['used_limit_of_property'] = $package->used_limit_for_property;
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    // public function get_languages(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'language_code' => 'required',
    //     ]);

    //     if (!$validator->fails()) {
    //         $language = Language::where('code', $request->language_code)->first();

    //         if ($language) {
    //             if ($request->web_language_file) {
    //                 $json_file_path = public_path('web_languages/' . $request->language_code . '.json');
    //             } else {
    //                 $json_file_path = public_path('languages/' . $request->language_code . '.json');
    //             }

    //             if (file_exists($json_file_path)) {
    //                 $json_string = file_get_contents($json_file_path);
    //                 $json_data = json_decode($json_string);

    //                 if ($json_data !== null) {
    //                     $language->file_name = $json_data;
    //                     $response['error'] = false;
    //                     $response['message'] = "Data Fetch Successfully";
    //                     $response['data'] = $language;
    //                 } else {
    //                     $response['error'] = true;
    //                     $response['message'] = "Invalid JSON format in the language file";
    //                 }
    //             } else {
    //                 $response['error'] = true;
    //                 $response['message'] = "Language file not found";
    //             }
    //         } else {
    //             $response['error'] = true;
    //             $response['message'] = "Language not found";
    //         }
    //     } else {
    //         $response['error'] = true;
    //         $response['message'] = $validator->errors()->first();
    //     }

    //     return response()->json($response);
    // }

    // public function get_languages(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'language_code' => 'required',

    //     ]);
    //     if (!$validator->fails()) {

    //         DB::enableQueryLog();

    //         $language = Language::where('code', $request->language_code);

    //         $result = $language->get();

    //         //  dd(DB::getQueryLog());

    //         if ($result) {
    //             $response['error'] = false;
    //             $response['message'] = "Data Fetch Successfully";
    //             $response['data'] = $result;
    //         } else {
    //             $response['error'] = false;
    //             $response['message'] = "No data found!";
    //             $response['data'] = [];
    //         }
    //     } else {
    //         $response['error'] = true;
    //         $response['message'] = $validator->errors()->first();
    //     }
    //     return response()->json($response);
    // }

    //Test code: 
    public function get_languages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language_code' => 'required',
        ]);

        if (!$validator->fails()) {
            $language = Language::where('code', $request->language_code)->first();

            if ($language) {
                $json_file_path = $request->web_language_file 
                    ? public_path('web_languages/' . $request->language_code . '.json') 
                    : public_path('languages/' . $request->language_code . '.json');

                if (file_exists($json_file_path)) {
                    $json_string = file_get_contents($json_file_path);
                    $json_data = json_decode($json_string, true); // Thêm 'true' để trả về mảng thay vì object
                    

                    if ($json_data !== null) {
                        // Chuyển mảng thành chuỗi JSON để tránh lỗi
                        $language->json_data = $json_data;
                    
                        $response['error'] = false;
                        $response['message'] = "Data Fetch Successfully";
                        $response['data'] = $language;
                    } else {
                        $response['error'] = true;
                        $response['message'] = "Invalid JSON format in the language file";
                        $response['data'] = $json_file_path;
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = "Language file not found";
                }
                            } else {
                $response['error'] = true;
                $response['message'] = "Language not found";
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }

        return response()->json($response);
    }

    
    public function get_payment_details(Request $request)
    {
        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);

        $payment = Payments::where('customer_id', $current_user);

        $result = $payment->get();

        //  dd(DB::getQueryLog());

        if (count($result)) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";



            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function paypal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
            'amount' => 'required'

        ]);
        if (!$validator->fails()) {
            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);
            $paypal = new Paypal();
            // url('') . config('global.IMG_PATH')
            $returnURL = url('api/app_payment_status');
            $cancelURL = url('api/app_payment_status');
            $notifyURL = url('webhook/paypal');
            // $package_id = $request->package_id;
            $package_id = $request->package_id;
            // Get product data from the database

            // Get current user ID from the session
            $paypal->add_field('return', $returnURL);
            $paypal->add_field('cancel_return', $cancelURL);
            $paypal->add_field('notify_url', $notifyURL);
            $custom_data = $package_id . ',' . $current_user;

            // // Add fields to paypal form


            $paypal->add_field('item_name', "package");
            $paypal->add_field('custom_id', json_encode($custom_data));

            $paypal->add_field('custom', ($custom_data));

            $paypal->add_field('amount', $request->amount);

            // Render paypal form
            $paypal->paypal_auto_form();
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
    }
    public function app_payment_status(Request $request)
    {

        $paypalInfo = $request->all();

        if (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "completed") {

            $response['error'] = false;
            $response['message'] = "Your Purchase Package Activate Within 10 Minutes ";
            $response['data'] = $paypalInfo['txn_id'];
        } elseif (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "authorized") {

            $response['error'] = false;
            $response['message'] = "Your payment has been Authorized successfully. We will capture your transaction within 30 minutes, once we process your order. After successful capture Ads wil be credited automatically.";
            $response['data'] = $paypalInfo;
        } else {
            $response['error'] = true;
            $response['message'] = "Payment Cancelled / Declined ";
            $response['data'] = (isset($_GET)) ? $paypalInfo : "";
        }
        // print_r(json_encode($response));
        return (response()->json($response));
    }
    public function get_payment_settings(Request $request)
    {

        $payment_settings =
            Setting::select('type', 'data')->whereIn('type', ['paypal_business_id', 'sandbox_mode', 'paypal_gateway', 'razor_key', 'razor_secret', 'razorpay_gateway', 'paystack_public_key', 'paystack_secret_key', 'paystack_currency', 'paystack_gateway', 'stripe_publishable_key', 'stripe_currency', 'stripe_gateway', 'stripe_secret_key']);

        $result = $payment_settings->get();

        //  dd(DB::getQueryLog());

        if (count($result)) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return (response()->json($response));
    }
    public function send_message(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'sender_id' => 'required',
            'receiver_id' => 'required',
            'message' => 'required',
            'property_id' => 'required',
        ]);
        $fcm_id = array();
        if (!$validator->fails()) {

            $chat = new Chats();
            $chat->sender_id = $request->sender_id;
            $chat->receiver_id = $request->receiver_id;
            $chat->property_id = $request->property_id;
            $chat->message = $request->message;
            $destinationPath = public_path('images') . config('global.CHAT_FILE');
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            // image upload

            if ($request->hasFile('file')) {
                // dd('in');
                $file = $request->file('file');
                $fileName = microtime(true) . "." . $file->getClientOriginalExtension();
                $file->move($destinationPath, $fileName);
                $chat->file = $fileName;
            } else {
                $chat->file = '';
            }

            $audiodestinationPath = public_path('images') . config('global.CHAT_AUDIO');
            if (!is_dir($audiodestinationPath)) {
                mkdir($audiodestinationPath, 0777, true);
            }
            if ($request->hasFile('audio')) {
                // dd('in');
                $file = $request->file('audio');
                $fileName = microtime(true) . "." . $file->getClientOriginalExtension();
                $file->move($audiodestinationPath, $fileName);
                $chat->audio = $fileName;
            } else {
                $chat->audio = '';
            }
            $chat->save();
            $customer = Customer::select('id', 'fcm_id', 'name', 'profile')->with([
                'usertokens' => function ($q) {
                    $q->select('fcm_id', 'id', 'customer_id');
                }
            ])->find($request->receiver_id);
            $property = Property::find($request->property_id);
            // dd($customer->toArray());
            if ($customer) {

                foreach ($customer->usertokens as $usertokens) {

                    array_push($fcm_id, $usertokens->fcm_id);
                }
                // $fcm_id = [$customer->usertokens->fcm_id];

                $username = $customer->name;
                $profile = $customer->profile;
            }
            $user_data = User::select('fcm_id', 'name')->get();

            if (!$customer && $property->added_by == 0) {
                $username = "Admin";
                $profile = "";

                foreach ($user_data as $user) {
                    array_push($fcm_id, $user->fcm_id);
                }
            };
            $customer = Customer::select('fcm_id', 'name')->find($request->sender_id);

            // print_r($fcm_id);
            $Property = Property::find($request->property_id);
            $fcmMsg = array(
                'title' => 'Message',
                'message' => $request->message,
                'type' => 'chat',
                'body' => $request->message,
                'sender_id' => $request->sender_id,
                'receiver_id' => $request->receiver_id,
                'file' => $chat->file,
                'username' => $username,
                'user_profile' => $profile,
                'audio' => $chat->audio,
                'date' => $chat->created_at,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default',
                'time_ago' => $chat->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true),
                'property_id' => $Property->id,
                'property_title_image' => $Property->title_image,
                'title' => $Property->title,
            );

            $send = send_push_notification($fcm_id, $fcmMsg);
            $response['error'] = false;
            $response['message'] = "Data Store Successfully";
            $response['id'] = $chat->id;
            $response['data'] = $send;
            // $chat->sender_id = $request->sender_id;
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return (response()->json($response));
    }
    public function get_messages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'property_id' => 'required'

        ]);
        if (!$validator->fails()) {
            $payload = JWTAuth::getPayload($this->bearerToken($request));
            $current_user = ($payload['customer_id']);
            // dd($current_user);

            $tempRow = array();
            $perPage = $request->per_page ? $request->per_page : 15; // Number of results to display per page
            $page = $request->page ?? 1; // Get the current page from the query string, or default to 1
            $chat = Chats::where('property_id', $request->property_id)
                ->where(function ($query) use ($request) {
                    $query->where('sender_id', $request->user_id)
                        ->orWhere('receiver_id', $request->user_id);
                })
                ->Where(function ($query) use ($current_user) {
                    $query->where('sender_id', $current_user)
                        ->orWhere('receiver_id', $current_user);
                })
                ->orderBy('created_at', 'DESC')
                //  ->get();
                ->paginate($perPage, ['*'], 'page', $page);

            // You can then pass the $chat object to your view to display the paginated results.
            // dd($chat->toArray());
            if ($chat) {

                $response['error'] = false;
                $response['message'] = "Data Fetch Successfully";
                $response['total_page'] = $chat->lastPage();
                $response['data'] = $chat;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function get_chats(Request $request)
    {
        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);
        $perPage = $request->per_page ? $request->per_page : 15; // Number of results to display per page
        $page = $request->page ?? 1;

        $chat = Chats::with(['sender', 'receiver'])->with('property')
            ->select('id', 'sender_id', 'receiver_id', 'property_id', 'created_at')
            ->where('sender_id', $current_user)
            ->orWhere('receiver_id', $current_user)
            ->orderBy('id', 'desc')
            ->groupBy('property_id')
            ->paginate($perPage, ['*'], 'page', $page);

        if (!$chat->isEmpty()) {

            $rows = array();

            $count = 1;

            $response['total_page'] = $chat->lastPage();

            foreach ($chat as $key => $row) {
                $tempRow = array();
                $tempRow['property_id'] = $row->property_id;
                $tempRow['title'] = $row->property->title;
                $tempRow['title_image'] = $row->property->title_image;
                $tempRow['date'] = $row->created_at;
                $tempRow['property_id'] = $row->property_id;
                if (!$row->receiver || !$row->sender) {
                    $user =
                        user::where('id', $row->sender_id)->orWhere('id', $row->receiver_id)->select('id')->first();

                    $tempRow['user_id'] = 0;
                    $tempRow['name'] = "Admin";
                    $tempRow['profile'] = '';

                    // $tempRow['fcm_id'] = $row->receiver->fcm_id;
                } else {
                    if ($row->sender->id == $current_user) {

                        $tempRow['user_id'] = $row->receiver->id;
                        $tempRow['name'] = $row->receiver->name;
                        $tempRow['profile'] = $row->receiver->profile;
                        $tempRow['firebase_id'] = $row->receiver->firebase_id;
                        $tempRow['fcm_id'] = $row->receiver->fcm_id;
                    }
                    if ($row->receiver->id == $current_user) {
                        $tempRow['user_id'] = $row->sender->id;
                        $tempRow['name'] = $row->sender->name;

                        $tempRow['profile'] = $row->sender->profile;
                        $tempRow['firebase_id'] = $row->sender->firebase_id;
                        $tempRow['fcm_id'] = $row->sender->fcm_id;
                    }
                }
                $rows[] = $tempRow;
                // $parameters[] = $arr;
                $count++;
            }


            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $rows;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_nearby_properties(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'city' => 'required',

        ]);
        if (!$validator->fails()) {
            $result = Property::select('id', 'price', 'latitude', 'longitude', 'propery_type')->where('city', 'LIKE', "%$request->city%")->where('status', 1)->get();
            $rows = array();
            $tempRow = array();
            $count = 1;

            if (!$result->isEmpty()) {

                foreach ($result as $key => $row) {
                    $tempRow['id'] = $row->id;
                    $tempRow['price'] = $row->price;
                    $tempRow['latitude'] = $row->latitude;
                    $tempRow['longitude'] = $row->longitude;
                    if ($row->propery_type == 0) {
                        $tempRow['property_type'] = "Sell";
                    } elseif ($row->propery_type == 1) {
                        $tempRow['property_type'] = "Rent";
                    } elseif ($row->propery_type == 2) {
                        $tempRow['property_type'] = "Sold";
                    } elseif ($row->propery_type == 3) {
                        $tempRow['property_type'] = "Rented";
                    }
                    $rows[] = $tempRow;

                    $count++;
                }
            }

            $response['error'] = false;
            $response['data'] = $rows;
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function update_property_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'property_id' => 'required'

        ]);
        if (!$validator->fails()) {
            $property = Property::find($request->property_id);
            $property->propery_type = $request->status;
            $property->save();
            $response['error'] = false;
            $response['message'] = "Data updated Successfully";
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function get_count_by_cities_categoris(Request $request)
    {
        // get count by category

        $categoriesWithCount = Category::withCount('properties')->get();
        $cat_arr = array();
        $city_arr = array();
        $agent_arr = array();

        foreach ($categoriesWithCount as $category) {

            array_push($cat_arr, ['category' => $category->category, 'Count' => $category->properties_count]);
        }
        $response['category_data'] = $cat_arr;
        $propertiesByCity = Property::groupBy('city')
            ->select('city', DB::raw('count(*) as count'))
            ->orderBy('count', 'DESC')->get();

        foreach ($propertiesByCity as $city) {
            $keyword = $city->city; // Get the keyword from the request

            $client = new Client();

            $_imgresponse = $client->request('GET', 'https://www.google.com/search?tbm=isch&q=HighResolutionImageFor' . urlencode($keyword));
            $html = $_imgresponse->getBody()->getContents();

            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches);

            $imageUrls = $matches[1] ?? [];

            if (count($imageUrls) > 1) {
                array_push($city_arr, ['City' => $city->city, 'Count' => $city->count, 'image' => $imageUrls[1]]);
            }
        }
        $response['city_data'] = $city_arr;

        return response()->json($response);
    }
    public function get_agents_details(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $agent_arr = array();
        $propertiesByAgent = Property::with([
            'customer' => function ($q) {
                $q->where('role', 1);
            }
        ])
            ->groupBy('added_by')
            ->select('added_by', DB::raw('count(*) as count'))->skip($offset)->take($limit)
            ->get();
        foreach ($propertiesByAgent as $agent) {
            if (count($agent->customer)) {
                array_push($agent_arr, ['agent' => $agent->added_by, 'Count' => $agent->count, 'customer' => $agent->customer]);
            }
        }
        if (count($agent_arr)) {
            $response['error'] = false;
            $response['message'] = "Data Fetch  Successfully";
            $response['agent_data'] = $agent_arr;
        } else {
            $response['error'] = false;
            $response['message'] = "No Data Found";
        }
        return response()->json($response);
    }
    public function get_facilities(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $facilities = OutdoorFacilities::all();

        // if (isset($request->search) && !empty($request->search)) {
        //     $search = $request->search;
        //     $facilities->where('category', 'LIKE', "%$search%");
        // }

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $facilities->where('id', '=', $id);
        }
        $result = $facilities->skip($offset);

        $total = $facilities->count();

        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_report_reasons(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $report_reason = report_reasons::all();

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $report_reason->where('id', '=', $id);
        }
        $result = $report_reason->skip($offset)->take($limit);

        $total = $report_reason->count();

        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function add_reports(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason_id' => 'required',
            'property_id' => 'required',



        ]);
        $payload = JWTAuth::getPayload($this->bearerToken($request));
        $current_user = ($payload['customer_id']);
        if (!$validator->fails()) {
            $report_count = user_reports::where('property_id', $request->property_id)->where('customer_id', $current_user)->get();
            if (!count($report_count)) {
                $report_reason = new user_reports();
                $report_reason->reason_id = $request->reason_id ? $request->reason_id : 0;
                $report_reason->property_id = $request->property_id;
                $report_reason->customer_id = $current_user;
                $report_reason->other_message = $request->other_message ? $request->other_message : '';



                $report_reason->save();


                $response['error'] = false;
                $response['message'] = "Report Submited Successfully";
            } else {
                $response['error'] = false;
                $response['message'] = "Already Reported";
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function delete_chat_message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required',


        ]);
        if (!$validator->fails()) {
            $chat = Chats::find($request->message_id);
            if ($chat) {
                $chat->delete();

                $response['error'] = false;
                $response['message'] = "Message Deleted Successfully";
            } else {
                $response['error'] = false;
                $response['message'] = "No Data Found";
            }
        }
        return response()->json($response);
    }
    //HuyTBQ
    //* START :: get_locations_wards   *//
    public function get_locations_wards(Request $request)
    {
        $districtCode = config('location.district_code');


        $result = LocationsWard::select('code', 'full_name', 'full_name_en', 'district_code', 'administrative_unit_id')
            ->whereNotNull('district_code')
            ->where('district_code', $districtCode)
            ->orderByRaw("CASE
                            WHEN full_name LIKE 'phường%' THEN 1
                            WHEN full_name LIKE 'Xã%' THEN 2
                            ELSE 3 END,
                          CAST(SUBSTRING_INDEX(full_name, ' ', -1) AS UNSIGNED),
                          full_name")
            ->get();

            $total = $result->count();

        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: get_locations_wards   *//
    //* START :: get_streets   *//
    public function get_locations_streets(Request $request)
    {
        $districtCode = config('location.district_code');

        $result = LocationsStreet::select('code', 'street_name', 'district_code', 'ward_code')
            ->whereNotNull('district_code')
            ->where('district_code', $districtCode)
            ->get();

        $total = $result->count();

        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: get_streets   *//

    //* START :: get_crm_host   *//
    public function get_crm_hosts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host_id' => 'required',
        ]);

        if (!$validator->fails()) {
            $id = $request->host_id;

            $host = CrmHost::find($id);
            if (!empty($host)) {

                $response['error'] = false;
                $response['data'] = $host;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: get_crm_host   *//
}
