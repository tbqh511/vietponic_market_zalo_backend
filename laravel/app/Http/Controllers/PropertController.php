<?php

namespace App\Http\Controllers;

use App\Models\AssignedOutdoorFacilities;
use App\Models\AssignParameters;
use App\Models\Category;
use App\Models\Chats;
use App\Models\Customer;

use App\Models\Notifications;
use App\Models\OutdoorFacilities;
use App\Models\parameter;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Models\PropertysInquiry;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Type;
use App\Models\Usertokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use kornrunner\Blurhash\Blurhash;
use Intervention\Image\ImageManagerStatic as Image;

class PropertController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'property')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $category = Category::all();
            return view('property.index', compact('category'));
        }
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!has_permissions('create', 'property')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $category = Category::where('status', '1')->get();


            $country = get_countries_from_json();
            $parameters = parameter::all();
            $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
            $facility = OutdoorFacilities::all();
            return view('property.create', compact('category', 'country', 'parameters', 'currency_symbol', 'facility'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->toArray());
        $arr = [];
        if (!has_permissions('read', 'property')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $request->validate([
                'gallery_images.*' => 'required|image|mimes:jpg,png,jpeg|max:2048',
                'title_image.*' => 'required|image|mimes:jpg,png,jpeg|max:2048',
            ]);

            $destinationPath = public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH');
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $destinationPathFor3d = public_path('images') . config('global.3D_IMG_PATH');
            if (!is_dir($destinationPathFor3d)) {
                mkdir($destinationPathFor3d, 0777, true);
            }
            $Saveproperty = new Property();
            $Saveproperty->category_id = $request->category;
            $Saveproperty->title = $request->title;
            $Saveproperty->description = $request->description;
            $Saveproperty->address = $request->address;
            $Saveproperty->client_address = $request->client_address;
            $Saveproperty->propery_type = $request->property_type;
            $Saveproperty->price = $request->price;
            $Saveproperty->package_id = 0;
            $Saveproperty->city = (isset($request->city)) ? $request->city : '';
            $Saveproperty->country = (isset($request->country)) ? $request->country : '';
            $Saveproperty->state = (isset($request->state)) ? $request->state : '';
            $Saveproperty->latitude = (isset($request->latitude)) ? $request->latitude : '';
            $Saveproperty->longitude = (isset($request->longitude)) ? $request->longitude : '';
            $Saveproperty->video_link = (isset($request->video_link)) ? $request->video_link : '';
            $Saveproperty->post_type = 0;
            $Saveproperty->added_by = 0;
            $Saveproperty->rentduration = $request->price_duration;

            if ($request->hasFile('title_image')) {
                $profile = $request->file('title_image');
                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                $profile->move($destinationPath, $imageName);
                $Saveproperty->title_image = $imageName;
                // $Saveproperty->title_image_hash = $image_hash;
            } else {
                $Saveproperty->title_image  = '';
            }
            if ($request->hasFile('3d_image')) {

                $profile = $request->file('3d_image');
                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                $profile->move($destinationPathFor3d, $imageName);
                $Saveproperty->threeD_image = $imageName;
            } else {
                $Saveproperty->threeD_image  = '';
            }
            $Saveproperty->save();

            $facility = OutdoorFacilities::all();
            foreach ($facility as $key => $value) {
                if ($request->has('facility' . $value->id) && $request->input('facility' . $value->id) != '') {
                    $facilities = new AssignedOutdoorFacilities();
                    $facilities->facility_id = $value->id;
                    $facilities->property_id = $Saveproperty->id;
                    $facilities->distance = $request->input('facility' . $value->id);
                    $facilities->save();
                }
                # code...
            }
            $parameters = parameter::all();
            foreach ($parameters as $par) {

                if ($request->has($par->id)) {
                    // echo "in";
                    $assign_parameter = new AssignParameters();
                    $assign_parameter->parameter_id = $par->id;
                    if (($request->hasFile($par->id))) {
                        $destinationPath = public_path('images') . config('global.PARAMETER_IMG_PATH');
                        if (!is_dir($destinationPath)) {
                            mkdir($destinationPath, 0777, true);
                        }
                        $imageName = microtime(true) . "." . ($request->file($par->id))->getClientOriginalExtension();
                        ($request->file($par->id))->move($destinationPath, $imageName);
                        $assign_parameter->value = $imageName;
                    } else {
                        $assign_parameter->value = is_array($request->input($par->id)) ? json_encode($request->input($par->id), JSON_FORCE_OBJECT) : ($request->input($par->id));
                    }
                    $assign_parameter->modal()->associate($Saveproperty);
                    $assign_parameter->save();
                    $arr = $arr + [$par->id => $request->input($par->id)];
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
                // dd("in");
                foreach ($request->file('gallery_images') as $file) {
                    $name = time() . rand(1, 100) . '.' . $file->extension();
                    $file->move($destinationPath, $name);
                    PropertyImages::create([
                        'image' => $name,
                        'propertys_id' => $Saveproperty->id
                    ]);
                }
            }
            /// END :: UPLOAD GALLERY IMAGE
            return back()->with('success', 'Successfully Added');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!has_permissions('update', 'property')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $category = Category::all()->where('status', '1')->mapWithKeys(function ($item, $key) {
                return [$item['id'] => $item['category']];
            });
            $category = Category::where('status', '1')->get();
            $list = Property::with('assignParameter.parameter')->where('id', $id)->get()->first();
            // dd($list->category_id);
            $paramaeter_ids = Category::find($list->category_id);
            // dd($paramaeter_ids['parameter_types']);
            DB::enableQueryLog();
            $parameters = parameter::all();
            $edit_parameters = parameter::with(['assigned_parameter' => function ($q) use ($id) {
                $q->where('modal_id', $id);
            }])->whereIn('id', explode(',', $paramaeter_ids['parameter_types']))->get();
            // dd(DB::getQueryLog());
            // dd($parameters->toArray());
            $country = get_countries_from_json();
            $state = get_states_from_json($list->country);
            $facility = OutdoorFacilities::with(['assign_facilities' => function ($q) use ($id) {
                $q->where('property_id', $id);
            }])->get();
            $assignfacility = AssignedOutdoorFacilities::where('property_id', $id)->get();

            $arr = json_decode($list->carpet_area);
            $par_arr = [];
            $par_id = [];
            $type_arr = [];
            foreach ($list->assignParameter as  $par) {
                $par_arr = $par_arr + [$par->parameter->name => $par->value];
                $par_id = $par_id + [$par->parameter->name => $par->value];
            }
            $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
            return view('property.edit', compact('category', 'state', 'facility', 'assignfacility', 'edit_parameters', 'country', 'list', 'id', 'par_arr', 'parameters', 'par_id', 'currency_symbol'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // dd($request->toArray());
        if (!has_permissions('update', 'property')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {

            $UpdateProperty = Property::with('assignparameter.parameter')->find($id);
            // dd($UpdateProperty->toArray());
            $destinationPath = public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH');
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $UpdateProperty->category_id = $request->category;
            $UpdateProperty->title = $request->title;
            $UpdateProperty->description = $request->description;
            $UpdateProperty->address = $request->address;
            $UpdateProperty->client_address = $request->client_address;
            $UpdateProperty->propery_type = $request->property_type;
            $UpdateProperty->price = $request->price;
            $UpdateProperty->propery_type = $request->property_type;
            $UpdateProperty->price = $request->price;
            $UpdateProperty->state = (isset($request->state)) ? $request->state : '';
            $UpdateProperty->country = (isset($request->country)) ? $request->country : '';
            $UpdateProperty->city = (isset($request->city)) ? $request->city : '';
            $UpdateProperty->latitude = (isset($request->latitude)) ? $request->latitude : '';
            $UpdateProperty->longitude = (isset($request->longitude)) ? $request->longitude : '';
            $UpdateProperty->video_link = (isset($request->video_link)) ? $request->video_link : '';

            $UpdateProperty->rentduration = $request->price_duration;



            if ($request->hasFile('title_image')) {

                $profile = $request->file('title_image');
                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                $profile->move($destinationPath, $imageName);

                if ($UpdateProperty->title_image != '') {
                    if (file_exists(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') .  $UpdateProperty->title_image)) {
                        unlink(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') . $UpdateProperty->title_image);
                    }
                }
                $UpdateProperty->title_image = $imageName;
            }
            if ($request->title_image_length == 0 && isset($request->title_image_length)) {
                $UpdateProperty->title_image = '';
            }
            $destinationPathFor3d = public_path('images') . config('global.3D_IMG_PATH');
            if (!is_dir($destinationPathFor3d)) {
                mkdir($destinationPathFor3d, 0777, true);
            }
            if ($request->hasFile('3d_image')) {


                $profile = $request->file('3d_image');
                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                $profile->move($destinationPathFor3d, $imageName);


                if ($UpdateProperty->threeD_image != '') {
                    if (file_exists(public_path('images') . config('global.3D_IMG_PATH') .  $UpdateProperty->threeD_image)) {
                        unlink(public_path('images') . config('global.3D_IMG_PATH') . $UpdateProperty->threeD_image);
                    }
                }
                $UpdateProperty->threeD_image = $imageName;
            }
            if ($request->_3d_image_length == 0 && isset($request->_3d_image_length)) {
                $UpdateProperty->threeD_image = '';
            }
            $UpdateProperty->update();

            AssignedOutdoorFacilities::where('property_id', $UpdateProperty->id)->delete();
            $facility = OutdoorFacilities::all();
            foreach ($facility as $key => $value) {

                if ($request->has('facility' . $value->id) && $request->input('facility' . $value->id) != '') {

                    $facilities = new AssignedOutdoorFacilities();
                    $facilities->facility_id = $value->id;
                    $facilities->property_id = $UpdateProperty->id;
                    $facilities->distance = $request->input('facility' . $value->id);
                    $facilities->save();
                }
                # code...
            }
            $parameters = parameter::all();

            AssignParameters::where('modal_id', $id)->delete();

            foreach ($parameters as $par) {

                if ($request->has($par->id)) {

                    $update_parameter = new AssignParameters();
                    $update_parameter->parameter_id = $par->id;


                    if (($request->hasFile($par->id))) {

                        $destinationPath = public_path('images') . config('global.PARAMETER_IMG_PATH');
                        if (!is_dir($destinationPath)) {
                            mkdir($destinationPath, 0777, true);
                        }
                        $imageName = microtime(true) . "." . ($request->file($par->id))->getClientOriginalExtension();
                        ($request->file($par->id))->move($destinationPath, $imageName);
                        $update_parameter->value = $imageName;
                    } else {

                        $update_parameter->value = is_array($request->input($par->id)) || $request->input($par->id) == null ? json_encode($request->input($par->id), JSON_FORCE_OBJECT) : ($request->input($par->id));
                    }

                    $update_parameter->modal()->associate($UpdateProperty);
                    $update_parameter->save();
                }
            }

            /// START :: UPLOAD GALLERY IMAGE

            $FolderPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH');
            if (!is_dir($FolderPath)) {
                mkdir($FolderPath, 0777, true);
            }


            $destinationPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . "/" . $UpdateProperty->id;
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            if ($request->hasfile('gallery_images')) {
                foreach ($request->file('gallery_images') as $file) {
                    $name = time() . rand(1, 100) . '.' . $file->extension();
                    $file->move($destinationPath, $name);

                    PropertyImages::create([
                        'image' => $name,
                        'propertys_id' => $UpdateProperty->id
                    ]);
                }
            }

            /// END :: UPLOAD GALLERY IMAGE

            return redirect('property')->with('success', 'Successfully Update');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (env('DEMO_MODE') && Auth::user()->email != "superadmin@gmail.com") {
            return redirect()->back()->with('error', 'This is not allowed in the Demo Version');
        }
        if (!has_permissions('delete', 'property')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $property = Property::find($id);

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
                // rmdir(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $property->id);
                Notifications::where('propertys_id', $id)->delete();
                return back()->with('success', 'Property Deleted Successfully');
            } else {
                return back()->with('error', 'Something Wrong');
            }
        }
    }



    public function getPropertyList()
    {

        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'DESC';

        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }

        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }

        if (isset($_GET['sort'])) {
            $sort = $_GET['sort'];
        }

        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }

        $sql = Property::with('category')->with('customer')->with('assignParameter.parameter')->with('interested_users')->orderBy($sort, $order);
        // dd($sql->get()->toarray());

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('address', 'LIKE', "%$search%")->orwhereHas('category', function ($query) use ($search) {
                $query->where('category', 'LIKE', "%$search%");
            });
        }

        if ($_GET['status'] != '' && isset($_GET['status'])) {
            $status = $_GET['status'];
            $sql = $sql->where('status', $status);
        }
        if ($_GET['type'] != '' && isset($_GET['type'])) {
            $type = $_GET['type'];
            $sql = $sql->where('propery_type', $type);
        }

        if ($_GET['customer_id'] != '' && isset($_GET['customer_id'])) {
            $customer_id = $_GET['customer_id'];
            $sql = $sql->where('added_by', $customer_id);
        }

        if ($_GET['category'] != '' && isset($_GET['category'])) {
            $category_id = $_GET['category'];
            $sql = $sql->where('category_id', $category_id);
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }


        $res = $sql->get();
        //return $res;
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;


        $operate = '';
        foreach ($res as $row) {



            if (has_permissions('update', 'property')) {
                $operate = '<a  href="' . route('property.edit', $row->id) . '"  class="btn icon btn-primary btn-sm rounded-pill mt-2" title="Edit"><i class="fa fa-edit"></i></a>';
            }
            $operate1 = '<a  id="' . $row->id . '"  class="btn icon btn-primary btn-sm rounded-pill" data-status="' . $row->status . '" data-oldimage="' . $row->image . '" data-types="" data-bs-toggle="modal" data-bs-target="#editModal"  onclick="setValue(this.id);" title="Edit"><i class="bi bi-eye-fill"></i></a>';

            if (has_permissions('delete', 'property')) {
                $operate .= '<a href="' . route('property.destroy', $row->id) . '" onclick="return confirmationDelete(event);" class="btn icon btn-danger btn-sm rounded-pill mt-2" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-dark" title="Delete"><i class="bi bi-trash"></i></a>';
            }

            $status = $row->status == '1' ? 'checked' : '';
            $enable_disable =   '<div class="form-check form-switch center" style="padding-left: 5.2rem;">
            <input class="form-check-input switch1" id="' . $row->id . '"  onclick="chk(this);" type="checkbox" role="switch"' . $status . '>

            </div>';
            $interested_users = array();
            foreach ($row->interested_users as $interested_user) {

                if ($interested_user->property_id == $row->id) {

                    array_push($interested_users, $interested_user->customer_id);
                }
            }

            $tempRow['total_interested_users'] = count($interested_users);

            $tempRow['enble_disable'] = $enable_disable;

            $tempRow['id'] = $row->id;
            $tempRow['title'] = $row->title;
            $tempRow['category'] = isset($row->category->category) ? $row->category->category : '';
            $tempRow['address'] = $row->address;
            $tempRow['client_address'] = $row->client_address;
            $tempRow['furnished'] = ($row->furnished == '0') ? 'Furnished' : (($row->furnished == '1') ? 'Semi-Furnished' : 'Not-Furnished');
            if ($row->propery_type == 0) {
                $type = "Sell";
            } elseif ($row->propery_type == 1) {
                $type = "Rent";
            } elseif ($row->propery_type == 2) {
                $type = "Sold";
            } elseif ($row->propery_type == 3) {
                $type = "Rented";
            }
            $tempRow['propery_type'] = $type;
            $tempRow['price'] = $row->price;
            $tempRow['title_image'] = ($row->title_image != '') ? '<a class="image-popup-no-margins" href="' . $row->title_image . '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . $row->title_image . '" width="55"></a>' : '';

            $tempRow['3d_image'] = ($row->threeD_image != '') ? '<a class="class="photo360" href="' . $row->threeD_image . '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . $row->threeD_image . '" width="55"></a>' : '';

            $tempRow['interested_users'] = $operate1;

            $tempRow['status'] = ($row->status == '0') ? '<span class="badge rounded-pill bg-danger">Inactive</span>' : '<span class="badge rounded-pill bg-success">Active</span>';



            if ($row->added_by != 0) {
                $tempRow['added_by'] = $row->customer[0]->name;
                $tempRow['mobile'] = '*********';
            }
            if ($row->added_by == 0) {
                $tempRow['added_by'] = 'Admin';
                $tempRow['mobile'] = '*********';
            }

            foreach ($row->interested_users as $interested_user) {

                if ($interested_user->property_id == $row->id) {

                    $tempRow['interested_users_details'] = Customer::Where('id', $interested_user->customer_id);
                    // array_push($interested_users, $interested_user->customer_id);
                    // $s .= $interested_user->user_id . ',';
                }
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }
        // $cities =  json_decode(file_get_contents(public_path('json') . "/cities.json"), true);

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'property')) {
            $response['error'] = true;
            $response['message'] = PERMISSION_ERROR_MSG;
            return response()->json($response);
        } else {
            Property::where('id', $request->id)->update(['status' => $request->status]);
            $Property = Property::with('customer')->find($request->id);
            // dd(($Property->id));
            if (count($Property->customer) > 0) {
                // dd($Property->customer[0]->fcm_id );
                if ($Property->customer[0]->fcm_id != '' && $Property->customer[0]->notification == 1) {
                    // dd("in2");
                    //START :: Send Notification To Customer
                    $fcm_ids = array();
                    // dd($Property->customer[0]->id);
                    $customer_id = Customer::where('id', $Property->customer[0]->id)->where('isActive', '1')->where('notification', 1)->get();
                    if (count($customer_id)) {
                        $user_token = Usertokens::where('customer_id', $Property->customer[0]->id)->select('id', 'fcm_id')->get()->pluck('fcm_id')->toArray();
                        // dd($user_token);
                    }

                    $fcm_ids[] = $user_token;

                    $msg = "";
                    if (!empty($fcm_ids)) {
                        $msg = $Property->status == 1 ? 'Activate now by Adminstrator ' : 'Deactive now by Adminstrator ';
                        $registrationIDs = $fcm_ids[0];
                        // dd($registrationIDs);
                        $fcmMsg = array(
                            'title' => 'Property Updated',
                            'message' => 'Your Property Post ' . $msg,
                            'type' => 'property_inquiry',
                            'body' => 'Your Property Post ' . $msg,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',
                            'id' => $Property->id,
                        );
                        $result = send_push_notification($registrationIDs, $fcmMsg);
                        // dd($result);
                    }
                    //END ::  Send Notification To Customer

                    Notifications::create([
                        'title' => 'Property Updated',
                        'message' => 'Your Property Post ' . $msg,
                        'image' => '',
                        'type' => '1',
                        'send_type' => '0',
                        'customers_id' => $Property->customer[0]->id,
                        'propertys_id' => $Property->id
                    ]);
                }
            }
            $response['error'] = false;
            return response()->json($response);
        }
    }


    public function removeGalleryImage(Request $request)
    {
        // dd($request->toArray());
        if (!has_permissions('delete', 'slider')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;
            // dd($id);
            $getImage = PropertyImages::where('id', $id)->first();
            // dd($getImage);

            $image = $getImage->image;
            $propertys_id =  $getImage->propertys_id;

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
            return response()->json($response);
        }
    }


    public function getStatesByCountry(Request $request)
    {
        $country = $request->country;
        if ($country != '') {
            $state = get_states_from_json($country);

            if (!empty($state)) {
                $response['error'] = false;
                $response['data'] = $state;
            } else {
                $response['error'] = true;
                $response['message'] = "No data found!";
            }
        } else {
            $response['error'] = true;
            $response['message'] = "No data found!";
        }


        return response()->json($response);
    }
}
