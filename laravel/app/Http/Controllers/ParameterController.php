<?php

namespace App\Http\Controllers;

use App\Models\parameter;
use Illuminate\Http\Request;

class ParameterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        return view('parameter.index');
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
        // dd($request->toArray());
        $opt_value = json_encode($request->opt, JSON_FORCE_OBJECT);

        $destinationPath = public_path('images') . config('global.PARAMETER_IMAGE_PATH');
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }


        $request->validate([
            'parameter' => 'required',
            'image' => 'mimes:png,jpg,jpeg,svg'
        ]);
        $parameter = new parameter();
        $parameter->name = $request->parameter;
        $parameter->type_of_parameter = $request->options;
        $parameter->type_values = $opt_value;

        if ($request->hasFile('image')) {
            $profile = $request->file('image');
            $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
            $profile->move($destinationPath, $imageName);
            $parameter->image = $imageName;
        } else {
            $parameter->image  = '';
        }
        $parameter->save();



        return redirect()->back()->with('success', 'Parameter Successfully Added');
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



        $sql = parameter::orderBy($sort, $order);


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
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
            $tempRow['type'] = $row->type_of_parameter;
            $tempRow['value'] = is_array($row->type_values) ? implode(',', $row->type_values) : $row->type_values;
            $tempRow['image'] = ($row->image != '') ? '<a class="image-popup-no-margins" href="' . $row->image . '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . $row->image . '" width="55"></a>' : '';


            if (has_permissions('update', 'type')) {
                $operate = '<a  id="' . $row->id . '"  class="btn icon btn-primary btn-sm rounded-pill"  data-bs-toggle="modal" data-bs-target="#editModal"  onclick="setValue(this.id);" title="Edit"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';

                $tempRow['operate'] = $operate;
            }
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

        $request->validate([
            'edit_name' => 'required',
            'image' => 'mimes:png,jpg,jpeg,svg'
        ]);

        if (!has_permissions('update', 'type')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {


            $opt_value = json_encode($request->edit_opt, JSON_FORCE_OBJECT);

            $id =  $request->edit_id;

            $parameter = parameter::find($id);
            $parameter->name = ($request->edit_name) ? $request->edit_name : '';
            $parameter->type_of_parameter = ($request->edit_options) ? $request->edit_options : '';
            $parameter->type_values = $opt_value;

            $destinationPath = public_path('images') . config('global.PARAMETER_IMAGE_PATH');
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            if ($request->hasFile('image')) {
                $profile = $request->file('image');
                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();

                $profile->move($destinationPath, $imageName);

                if ($parameter->image != '') {
                    if (file_exists(public_path('images') . config('global.PARAMETER_IMAGE_PATH') .  $parameter->image)) {
                        unlink(public_path('images') . config('global.PARAMETER_IMAGE_PATH') . $parameter->image);
                    }
                }
                $parameter->image = $imageName;
            }

            $parameter->update();


            return back()->with('success', 'Parameter Successfully Update');
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
