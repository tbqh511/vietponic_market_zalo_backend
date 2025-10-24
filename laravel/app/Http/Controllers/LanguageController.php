<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session as FacadesSession;
use Throwable;

class LanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $language = Language::where('code', 'en')->first();
        FacadesSession::save();
        FacadesSession::put('language', $language);
        return view('settings.language');
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

        $language = new Language();
        $language->name = $request->name;
        $language->code = $request->code;
        $language->status = 0;
        $language->rtl = $request->rtl ? $request->rtl : 0;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = $request->code . '.' . $file->getClientOriginalExtension();
            $file->move(base_path('public/languages/'), $filename);
            $language->file_name = $filename;
        }

        if ($request->hasFile('file_for_panel')) {
            $file = $request->file('file_for_panel');
            $filename = $request->code . '.' . $file->getClientOriginalExtension();
            $file->move(base_path('resources/lang/'), $filename);
            $language->file_name = $filename;
        }
        $language->save();
        return back()->with('success', 'Language Successfully Added');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show()
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

        $sql = Language::orderBy($sort, $order);
        // dd($sql->toArray());


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('code', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }


        $res = $sql->get();
        // return $res;
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;


        $operate = '';
        $tempRow['type'] = '';
        $parameter_name_arr = [];
        foreach ($res as $row) {
            $tempRow['id'] = $row->id;
            $tempRow['name'] = $row->name;
            $tempRow['code'] = $row->code;
            $tempRow['rtl'] = $row->rtl ? "Yes" : "No";


            $tempRow['status'] = ($row->status == '0') ? '<span class="badge rounded-pill bg-danger">Inactive</span>' : '<span class="badge rounded-pill bg-success">Active</span>';
            $tempRow['file'] = '<a href=' . url('languages/' . $row->code . '.json') . '>' . 'View File' . '</a>';
            $tempRow['panel_file'] = '<a href=' . url('lang/en.json') . '>' . 'View File' . '</a>';
            $parameter_type_arr = explode(',', $row->parameter_types);

            $ids = isset($row->parameter_types) ? $row->parameter_types : '';
            $operate = '&nbsp;&nbsp; <a  id="' . $row->id . '"  class="btn icon btn-primary btn-sm rounded-pill" data-status="' . $row->status . '" data-oldimage="' . $row->image . '" data-types="' . $ids . '" data-bs-toggle="modal" data-bs-target="#editModal"  onclick="setValue(this.id);" title="Edit"><i class="fa fa-edit"></i></a>';


            $operate .= '&nbsp;&nbsp;<a href="' . route('language.destroy', $row->id) . '" onclick="return confirmationDelete(event);" class="btn icon btn-danger btn-sm rounded-pill" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-dark" title="Delete"><i class="bi bi-trash"></i></a>';
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
        // dd($request->toArray());

        $language = Language::find($request->edit_id);
        $language->name = $request->edit_language_name;
        $language->code = $request->edit_language_code;
        $language->status = 0;
        $language->rtl = $request->edit_rtl == "on" ? true : false;

        if ($request->hasFile('edit_json')) {


            $file = $request->file('edit_json');
            $filename = $request->edit_language_code . '.' . $file->getClientOriginalExtension();
            if ($file->getClientOriginalExtension() != 'json') {
                return back()->with('error', 'Invalid File Type');
            }
            if (file_exists(base_path('public/languages/') . $filename)) {
                File::delete(base_path('public/languages/'), $filename);
            }
            $file->move(base_path('public/languages/'), $filename);
            $language->file_name = $filename;
        }
        if ($request->hasFile('edit_json_admin')) {


            $file = $request->file('edit_json_admin');
            $filename = $request->edit_language_code . '.' . $file->getClientOriginalExtension();
            if ($file->getClientOriginalExtension() != 'json') {
                return back()->with('error', 'Invalid File Type');
            }
            if (file_exists(base_path('resources/lang/') . $filename)) {
                File::delete(base_path('resources/lang/'), $filename);
            }
            $file->move(base_path('resources/lang/'), $filename);
            $language->file_name = $filename;
        }




        $language->save();
        return back()->with('success', 'Language Updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        if (!has_permissions('delete', 'property')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $language = Language::find($id);

            if ($language->delete()) {
                if ($language->title_image != '') {
                    if (file_exists(base_path('public/languages/'), $language->file)) {
                        unlink(base_path('public/languages/'), $language->file);
                    }
                }


                return redirect()->back()->with('success', 'Language Deleted Successfully');
            } else {
                return redirect()->back()->with('error', 'Something Wrong');
            }
        }
    }
    public function set_language(Request $request)
    {
        FacadesSession::put('locale', $request->lang);
        $language = Language::where('code', $request->lang)->first();
        FacadesSession::save();
        FacadesSession::put('language', $language);
        app()->setLocale(FacadesSession::get('locale'));
        return redirect()->back();
    }
    public function downloadPanelFile()
    {
        $file = base_path("resources/lang/en.json");
        $filename = 'en.json';

        return Response::download($file, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
    public function downloadAppFile()
    {
        $file = public_path("languages/en.json");
        $filename = 'en.json';

        return Response::download($file, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
