<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Customer;
use App\Models\Notifications;
use App\Models\Usertokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvertisementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //

        return view('advertisement.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        //
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

        // \DB::enableQueryLog(); // Enable query log
        $sql = Advertisement::with('customer')->with('property')->orderBy($sort, $order);

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('description', 'LIKE', "%$search%");
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }
        $res = $sql->get();
        // dd(\DB::getQueryLog());

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;


        $status = '';
        $operate = '';
        foreach ($res as $row) {

            $operate = '<a  id="' . $row->id . '"  class="btn icon btn-primary btn-sm rounded-pill" data-status="' . $row->status . '" data-oldimage="' . $row->image . '" data-types="' . $row->id . '" data-bs-toggle="modal" data-bs-target="#editModal"  onclick="setValue(this.id);" title="Edit"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';


            $tempRow['id'] = $row->id;
            $tempRow['title'] = $row->title;
            $tempRow['type'] = $row->type;
            
            
            
             $tempRow['image'] = '<a class="image-popup-no-margins" href="' . $row->property->title_image . '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . $row->property->title_image . '" width="55"></a>';
            
            $tempRow['start_date'] = $row->start_date;
            $tempRow['end_date'] = $row->end_date;
            $tempRow['user_name'] = $row->customer->name;
            $tempRow['user_contact'] = $row->user_contact;
            $tempRow['is_enable'] = ($row->is_enable == '0') ? '<span class="badge  rounded-pill bg-danger">Disable</span>' : '<span class="badge rounded-pill bg-success">Enable</span>';
            $tempRow['user_email'] = $row->user_email;
            $status = $row->is_enable == '1' ? 'checked' : '';
            $enable_disable =   '<div class="form-check form-switch" style="padding-left: 5.2rem;">
                 <input class="form-check-input switch1" id="' . $row->id . '"  onclick="chk(this);" type="checkbox" role="switch"' . $status . '>

            </div>';

            $tempRow['enble_disable'] = $enable_disable;

            if ($row->status == 0) {
                $status = 'Approved';
            }
            if ($row->status == 1) {
                $status = 'Pending';
            }
            if ($row->status == 2) {
                $status = 'Rejected';
            }
            $tempRow['status'] = $status;

            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (!has_permissions('update', 'property')) {
            $response['error'] = true;
            $response['message'] = PERMISSION_ERROR_MSG;
            return response()->json($response);
        } else {
            Advertisement::find($request->id)->update(['status' => $request->edit_adv_status]);

            $adv = Advertisement::with('customer')->find($request->id);
            // dd($adv);
            $status = $adv->status;
            if ($adv->customer->notification == 1) {
                if ($status == '0') {
                    $status_text  = 'Approved';
                } else if ($status == '1') {
                    $status_text  = 'Pending';
                } else if ($status == '2') {
                    $status_text  = 'Rejected';
                }
                //START :: Send Notification To Customer
                $fcm_ids = array();
                
                
                
                
                
                  
             $advertisement = Advertisement::with('customer')->find($request->id);
          
            // dd(($Property->id));
            if (($advertisement->customer)) {
                // dd($advertisement->customer->fcm_id );
                if ($advertisement->customer->notification == 1) {
                    // dd("in2");
                    //START :: Send Notification To Customer
                    $fcm_ids = array();
                    // dd($Property->customer[0]->id);
                    $customer_id = Customer::where('id', $advertisement->customer->id)->where('isActive', '1')->where('notification', 1)->get();
                    if (count($customer_id)) {
                        $user_token = Usertokens::where('customer_id', $advertisement->customer->id)->select('id', 'fcm_id')->get()->pluck('fcm_id')->toArray();
                        // dd($user_token);
                    }

                    $fcm_ids[] = $user_token;

                    $msg = "";
                    if (!empty($fcm_ids)) {
                        $msg = $advertisement->is_enable == 1 ? 'Activate now by Adminstrator ' : 'Deactive now by Adminstrator ';
                        $registrationIDs = $fcm_ids[0];
                        // dd($registrationIDs);
                            $fcmMsg = array(
                        'title' => 'Advertisement Request',
                        'message' => 'Advertisement Request Is ' . $status_text,
                        'type' => 'advertisement_request',
                        'body' => 'Advertisement Request Is ' . $status_text,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => $adv->id,
                    );
                        $result = send_push_notification($registrationIDs, $fcmMsg);
                        // dd($result);
                    }
                    //END ::  Send Notification To Customer

                    Notifications::create([
                    'title' => 'Property Inquiry Updated',
                    'message' => 'Your Advertisement Request is ' . $status_text,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $advertisement->customer->id,
                    'propertys_id' => $advertisement->id
                ]);
                }
            
            
            }
            
                
                
                
                
                
                
                //  if (!empty($adv->customer)) {
                    

                //     $customer_id = Customer::where('id', $adv->customer->id)->where('isActive', '1')->where('notification', 1)->get();
                //     // dd($customer_id->toArray());
                //     if (count($customer_id)) {
                //             //   DB::enableQueryLog();

                //         $user_token = Usertokens::where('customer_id', $adv->customer->id)->select('id', 'fcm_id')->get()->pluck('fcm_id')->toArray();
                //                 // dd(DB::getQueryLog());

                //         // dd( $adv->customer->id);
                //     }
                //     $fcm_ids[] = $user_token;
                //     // dd($fcm_ids);

                //     $registrationIDs = array_filter($fcm_ids);
                //     $fcmMsg = array(
                //         'title' => 'Advertisement Request',
                //         'message' => 'Advertisement Request Is ' . $status_text,
                //         'type' => 'advertisement_request',
                //         'body' => 'Advertisement Request Is ' . $status_text,
                //         'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                //         'sound' => 'default',
                //         'id' => $adv->id,
                //     );
                   
                //   send_push_notification($registrationIDs, $fcmMsg);
                // }
                //END ::  Send Notification To Customer

                // Notifications::create([
                //     'title' => 'Property Inquiry Updated',
                //     'message' => 'Your Advertisement Request is ' . $status_text,
                //     'image' => '',
                //     'type' => '1',
                //     'send_type' => '0',
                //     'customers_id' => $adv->customer->id,
                //     'propertys_id' => $adv->id
                // ]);
            }

            return back()->with('success', 'Property status update Successfully');
        }
    }

    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'property')) {
            $response['error'] = true;
            $response['message'] = PERMISSION_ERROR_MSG;
            return response()->json($response);
        } else {
            Advertisement::where('id', $request->id)->update(['is_enable' => $request->is_enable]);
            
            
            
            
            
            
             $advertisement = Advertisement::with('customer')->find($request->id);
          
            // dd(($Property->id));
            if (($advertisement->customer)) {
                // dd($advertisement->customer->fcm_id );
                if ($advertisement->customer->notification == 1) {
                    // dd("in2");
                    //START :: Send Notification To Customer
                    $fcm_ids = array();
                    // dd($Property->customer[0]->id);
                    $customer_id = Customer::where('id', $advertisement->customer->id)->where('isActive', '1')->where('notification', 1)->get();
                    if (count($customer_id)) {
                        $user_token = Usertokens::where('customer_id', $advertisement->customer->id)->select('id', 'fcm_id')->get()->pluck('fcm_id')->toArray();
                        // dd($user_token);
                    }

                    $fcm_ids[] = $user_token;

                    $msg = "";
                    if (!empty($fcm_ids)) {
                        $msg = $advertisement->is_enable == 1 ? 'Activate now by Adminstrator ' : 'Deactive now by Adminstrator ';
                        $registrationIDs = $fcm_ids[0];
                        // dd($registrationIDs);
                        $fcmMsg = array(
                            'title' => 'Advertisement status Updated',
                            'message' => 'Your Advertisement ' . $msg,
                            'type' => 'Advertisement',
                            'body' => 'Your Advertisement ' . $msg,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',
                            'id' => $advertisement->id,
                        );
                        $result = send_push_notification($registrationIDs, $fcmMsg);
                        // dd($result);
                    }
                    //END ::  Send Notification To Customer

                    Notifications::create([
                        'title' => 'advertisement Updated',
                        'message' => 'Your advertisement ' . $msg,
                        'image' => '',
                        'type' => '1',
                        'send_type' => '0',
                        'customers_id' => $advertisement->customer->id,
                        'propertys_id' => $advertisement->id
                    ]);
                }
            
            
            }
            

            $response['error'] = false;
            return response()->json($response);
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
        //
    }
}

