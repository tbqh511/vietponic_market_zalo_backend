<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Notifications;
use App\Models\Property;
use App\Models\Usertokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Claims\Custom;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'notification')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $property_list = Property::all();
            return view('notification.index', compact('property_list'));
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


        if (!has_permissions('create', 'notification')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $get_fcm_key = DB::table('settings')->select('data')->where('type', 'fcm_key')->first();

            if ($get_fcm_key->data != '') {
                $request->validate(
                    [
                        'file' => 'image|mimes:jpeg,png,jpg',
                        'type' => 'required',
                        'send_type' => 'required',
                        'user_id' => 'required_if:users,==,selected',
                        'title' => 'required',
                        'message' => 'required',
                    ],
                    [
                        'user_id.*' => 'Select User From Table',
                    ]
                );





                $imageName = '';
                if ($request->hasFile('file')) {
                    $destinationPath = public_path('images') . config('global.NOTIFICATION_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    // image upload
                    $image = $request->file('file');
                    $imageName = microtime(true) . "." . $image->getClientOriginalExtension();
                    $image->move($destinationPath, $imageName);
                }

                if ($request->send_type == 1) {

                    $user_id = '';
                    $customer_ids = Customer::select('fcm_id', 'id')->where("fcm_id", "!=", "")->where('isActive', '1')->where('notification', 1)->get()->pluck('id')->toArray();

                    $fcm_ids = Usertokens::select('fcm_id')->whereIn('customer_id', $customer_ids)->get()->pluck('fcm_id')->toArray();
                } else {
                    $user_id = $request->user_id;
                    $ids = explode(',', $request->fcm_id);

                    $customer_ids = Customer::select('fcm_id', 'id')->whereIn("fcm_id", $ids)->where('isActive', '1')->where('notification', 1)->get()->pluck('id')->toArray();
                    $fcm_ids = Usertokens::select('fcm_id')->whereIn('customer_id', $customer_ids)->get()->pluck('fcm_id')->toArray();
                }
                $type = 0;
                if (isset($request->property)) {
                    $type = 2;
                    $propertys_id = $request->property;
                } else {
                    $type = $request->type;
                }
                Notifications::create([
                    'title' => $request->title,
                    'message' => $request->message,
                    'image' => $imageName,
                    'type' => $type,
                    'send_type' => $request->send_type,
                    'customers_id' => $user_id,
                    'propertys_id' => isset($propertys_id) ? $propertys_id : 0
                ]);

                $img = ($imageName != '') ? url('') . config('global.IMG_PATH') . config('global.NOTIFICATION_IMG_PATH') . $imageName : "";
                // dd($fcm_ids);

                //START :: Send Notification To Customer
                if (!empty($fcm_ids)) {

                    $registrationIDs = array_filter($fcm_ids);
                    // dd($registrationIDs);
                    $fcmMsg = array(
                        'title' => $request->title,
                        'message' => $request->message,
                        "image" => $img,
                        'type' => 'default',
                        'body' => $request->message,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',

                    );
                    send_push_notification($registrationIDs, $fcmMsg);
                    //   dd($send);
                }
                //END ::  Send Notification To Customer
                return back()->with('success', 'Message Send Successfully');
            } else {
                return back()->with('error', 'FCM Key Is Missing');
            }
        }
    }




    public function destroy(Request $request)
    {
        if (has_permissions('delete', 'notification')) {
            $id = $request->id;
            $image = $request->image;
            $destinationPath = public_path('images') . config('global.NOTIFICATION_IMG_PATH');
            if (Notifications::where('id', $id)->delete()) {
                if ($image != '') {
                    if (file_exists($destinationPath . $image)) {
                        unlink($destinationPath . $image);
                    }
                }
                return response()->json([
                    'error' => false,
                    'message' => "Notification Delete"
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "Something Wrong"
                ]);
            }
        } else {
            return response()->json([
                'error' => true,
                'message' => PERMISSION_ERROR_MSG
            ]);
        }
    }



    public function notificationList()
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



        // $sql = Notifications::orderBy($sort, $order);
        $sql = Notifications::where('id', '!=', 0);


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('message', 'LIKE', "%$search%");
        }

        // $sql = $sql->where('type', '0');

        $total = $sql->count();

        // if (isset($_GET['limit'])) {
        //     $sql->skip($offset)->take($limit);
        // }
        $sql = $sql->orderBy($sort, $order)->skip($offset)->take($limit);

        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;


        $operate = '';
        foreach ($res as $row) {
            if (has_permissions('delete', 'notification')) {
                $operate = '<a data-id=' . $row->id . ' data-image="' . $row->image . '" class="btn icon btn-danger btn-sm rounded-pill mt-2 delete-data" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-dark" title="Delete"><i class="bi bi-trash"></i></a>';
            }
            $type = '';
            if ($row->type == 0) {
                $type = 'General Notification';
            }
            if ($row->type == 1) {
                $type = 'Inquiry Notification';
            }
            if ($row->type == 2) {
                $type = 'Property Notification';
            }
            $tempRow['count'] = $count;
            $tempRow['id'] = $row->id;
            $tempRow['type'] = $type;
            $tempRow['send_type'] = ($row->send_type == 0) ? 'Selected' : 'All';
            $tempRow['title'] = $row->title;
            $tempRow['message'] = $row->message;
            $tempRow['customers_id'] = $row->customers_id;
            $tempRow['created_at'] = $row->created_at->diffForHumans();;
            $tempRow['image'] = ($row->image != '') ? '<a class="image-popup-no-margins" href="' . config('global.IMG_PATH') . config('global.NOTIFICATION_IMG_PATH')  . $row->image . '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . config('global.IMG_PATH') . config('global.NOTIFICATION_IMG_PATH')  . $row->image . '" width="55"></a>' : ''; //(!empty($row->image)) ? '<a href=' . $image . ' data-lightbox="Images"><img src="' . $image . '" height=50, width=50 ></a>' : 'No Image';
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function multiple_delete(Request $request)
    {
        if (has_permissions('delete', 'notification')) {
            $id = $request->id;

            $res = Notifications::whereIn('id', explode(',', $id))->get();

            $destinationPath = public_path('images') . config('global.NOTIFICATION_IMG_PATH');
            foreach ($res as $row) {
                if ($row->image != '') {
                    if (file_exists($destinationPath . $row->image)) {
                        unlink($destinationPath . $row->image);
                    }
                }
            }


            if (Notifications::whereIn('id', explode(',', $id))->delete()) {
                return response()->json([
                    'error' => false,
                    'message' => 'Multiple Delete'
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Something Wrong'
                ]);
            }
        } else {
            return response()->json([
                'error' => true,
                'message' => PERMISSION_ERROR_MSG
            ]);
        }
    }
}
