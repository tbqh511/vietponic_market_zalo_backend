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
        // Return user data (used by AJAX or direct requests)
        $user = User::findOrFail($id);
        return $user;
    }

    public function edit($id)
    {
        // Keep returning raw user data for AJAX edit modal population
        $user_data = User::findOrFail($id);
        return $user_data;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id = null)
    {
        // Support both resource-style PUT/PATCH (/users/{id}) and custom POST (users-update with edit_id)
        if (!has_permissions('update', 'users_accounts')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }

        $targetId = $id ?? $request->input('edit_id');
        $user = User::findOrFail($targetId);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'status' => 'nullable|in:0,1',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->permissions = isset($request->Editpermissions) ? json_encode($request->Editpermissions) : $user->permissions;
        $user->status = isset($data['status']) ? $data['status'] : $user->status;
        $user->save();

        return redirect()->back()->with('success', 'User Update Successfully');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        if (!has_permissions('delete', 'users_accounts')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }

        $user = User::findOrFail($id);
        $user->delete();
        return redirect()->back()->with('success', 'User deleted successfully');
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
                // delete button (form) - uses resource route DELETE /users/{id}
                $operate .= '&nbsp;&nbsp;<form method="POST" action="' . url('users/' . $row->id) . '" style="display:inline-block" onsubmit="return confirm(\'Delete this user?\')">' .
                    '<input type="hidden" name="_token" value="' . csrf_token() . '">' .
                    '<input type="hidden" name="_method" value="DELETE">' .
                    '<button class="btn icon btn-danger btn-sm rounded-pill" title="Delete"><i class="fa fa-trash"></i></button>' .
                    '</form>';
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
