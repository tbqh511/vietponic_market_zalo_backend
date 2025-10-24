<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $system_modules = config('rolepermission');


        return view('users.users', compact('system_modules'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!has_permissions('create', 'users_accounts')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->Password),
                'permissions' => isset($request->permissions) ? json_encode($request->permissions) : '',
                'type' => 1,
            ]);
            return redirect()->back()->with('success', 'User Insert Successfully');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    }

    public function edit($id)
    {
        $user_data = User::find($id);
        return $user_data;
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

        if (!has_permissions('update', 'users_accounts')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $id = $request->edit_id;
            $update =  User::find($id);
            $update->name = $request->name;
            $update->email = $request->email;
            $update->permissions = isset($request->Editpermissions) ? json_encode($request->Editpermissions) : '';
            $update->status = $request->status;
            $update->save();
            return redirect()->back()->with('success', 'User Update Successfully');
        }
    }

    public function userList()
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



        $sql = User::orderBy($sort, $order);


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
        }


        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }


        $res = $sql->where('type', '=', '1')->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;



        foreach ($res as $row) {
            $permission = ($row->permissions != '') ? base64_encode($row->permissions) : '';
            $operate = '<a  id="' . $row->id . '" data-permission="' . $permission . '" data-id="' . $row->id . '"class="btn icon btn-primary btn-sm rounded-pill editdata"  data-bs-toggle="modal" data-bs-target="#editUsereditModal1"  title="Edit"><i class="fa fa-edit"></i></a>';
            $operate .= '&nbsp;&nbsp;<a  id="' . $row->id . '" data-bs-toggle="modal"  class="btn icon btn-primary btn-sm rounded-pill" data-bs-target="#resetpasswordmodel" onclick="setpasswordValue(this.id);"><i class="bi bi-key text-dark-50"></i></a>';
            $tempRow['id'] = $row->id;
            $tempRow['name'] = $row->name;
            $tempRow['email'] = $row->email;
            $tempRow['status'] = ($row->status == '0') ? '<span class="badge rounded-pill bg-danger">Inactive</span>' : '<span class="badge rounded-pill bg-success">Active</span>';
            $tempRow['permissions'] = json_decode($row->permissions);
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }



    public function resetpassword(Request $request)
    {
        if (!has_permissions('update', 'users_accounts')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $id = $request->pass_id;
            User::where('id', $id)->update(['password' => Hash::make($request->confPassword)]);
            return redirect()->back()->with('success', 'Password Reset Successfully');
        }
    }
    public function updateFCMID(Request $request)
    {
        $user = User::find($request->id);
        $user->fcm_id = $request->token;
        $user->save();
    }
}
