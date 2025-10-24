<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        return view('customer.index');
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
        if (!has_permissions('delete', 'customer')) {
            $response['error'] = true;
            $response['message'] = PERMISSION_ERROR_MSG;
            return response()->json($response);
        } else {

            Customer::where('id', $request->id)->update(['isActive' => $request->status]);
            $response['error'] = false;
            return response()->json($response);
        }
    }




    public function customerList()
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



        $sql = Customer::orderBy($sort, $order);


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('mobile', 'LIKE', "%$search%");
        }


        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }


        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;


        $operate = '';
        foreach ($res as $row) {
            $tempRow['id'] = $row->id;
            $tempRow['name'] = $row->name;
            $tempRow['email'] = $row->email;
            $tempRow['mobile'] = $row->mobile;
            $tempRow['address'] = $row->address;
            $tempRow['firebase_id'] = $row->firebase_id;
            $tempRow['isActive'] = ($row->isActive == '0') ? '<span class="badge rounded-pill bg-danger">Inactive</span>' : '<span class="badge rounded-pill bg-success">Active</span>';
            $tempRow['profile'] = ($row->profile != '') ? '<a class="image-popup-no-margins" href="' . $row->profile . '" width="55" height="55"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . $row->profile . '" width="55" height="55"></a>' : '';

            $tempRow['fcm_id'] = $row->fcm_id;

            $isActive = $row->isActive == '1' ? 'checked' : '';

            $operate =   '<div class="form-check form-switch" style="justify-content: center;display: flex;">
         <input class="form-check-input switch1" id="' . $row->id . '"  onclick="chk(this);" type="checkbox" role="switch"' . $isActive . '>

            </div>';

            // $tempRow['enble_disable'] = $enable_disable;
            // if ($row->isActive == '0') {
            //     $operate =   '&nbsp;<a id="' . $row->id . '" class="btn icon btn-primary btn-sm rounded-pill" onclick="return active(this.id);" title="Enable"><i class="bi bi-eye-fill"></i></a>';
            // } else {
            //     $operate =   '&nbsp;<a id="' . $row->id . '" class="btn icon btn-danger btn-sm rounded-pill" onclick="return disable(this.id);" title="Disable"><i class="bi bi-eye-slash-fill"></i></a>';
            // }

            $tempRow['customertotalpost'] =  '<a href="' . url('property') . '?customer=' . $row->id . '">' . $row->customertotalpost . '</a>';
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
