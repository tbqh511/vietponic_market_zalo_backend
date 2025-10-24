<?php

namespace App\Http\Controllers;

use App\Models\Slider;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Property;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class SliderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'slider')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $slider = Slider::select('id', 'image', 'sequence')->orderBy('sequence', 'ASC')->get();

            $category = Category::select('id', 'category')->where('status', 1)->get();
            return view('slider.index', compact('slider', 'category'));
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

        if (!has_permissions('create', 'slider')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {

            $request->validate([
                'image.*' => 'required|image|mimes:jpg,png,jpeg|max:2048',
            ]);

            $destinationPath = public_path('images') . config('global.SLIDER_IMG_PATH');

            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $name = '';
            if ($request->hasfile('image')) {


                foreach ($request->file('image') as $file) {

                    $name = time() . rand(1, 100) . '.' . $file->extension();

                    $file->move($destinationPath, $name);

                    $imagePath = $destinationPath . $name;

                    //  Image::load($imagePath)->quality(70)->format(Manipulations::FORMAT_JPG)->save($imagePath);


                }
            }
            Slider::create([
                'image' => ($name) ? $name : '',
                'category_id' => (isset($request->category)) ? $request->category : 0,
                'propertys_id' => (isset($request->property)) ? $request->property : 0
            ]);
            return redirect()->back()->with('success', 'slider has been successfully added');
        }
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
        if (!has_permissions('update', 'slider')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {


            $id = $request->pk;
            $value = $request->value;

            Slider::where('id', $id)->update(['sequence' => $value]);
            return true;
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
        if (!has_permissions('delete', 'slider')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $getImage = Slider::where('id', $id)->first();
            $image = $getImage->image;

            if (Slider::where('id', $id)->delete()) {
                if (file_exists(public_path('images') . config('global.SLIDER_IMG_PATH') . $image) && is_file((file_exists(public_path('images') . config('global.SLIDER_IMG_PATH') . $image)))) {
                    unlink(public_path('images') . config('global.SLIDER_IMG_PATH') . $image);
                }
                return back()->with('success', 'slider delete successfully');
            } else {
                return back()->with('error', 'something is wrong !!!');
            }
        }
    }


    public function getPropertyByCategory(Request $request)
    {
        $id = $request->id;
        if ($id != '') {
            $Property = Property::with('customer')->where('category_id', $id)->get();

            //return $Property;
            if (!$Property->isEmpty()) {
                $rows = array();
                $tempRow = array();
                $count = 1;

                foreach ($Property as $row) {
                    // print_r($row->toArray());
                    $tempRow['id']  = $row->id;
                    $tempRow['title']  = $row->title;
                    $tempRow['name']  = (count($row->customer) > 0) ? $row->customer[0]->name : 'Administrator';
                    $rows[] = $tempRow;
                    $count++;
                }
                $response['error'] = false;
                $response['data'] = $rows;
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



    public function sliderList()
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



        $sql = Slider::with('category')->with('property')->orderBy($sort, $order);


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhereHas('category', function ($query) use ($search) {
                $query->where('category', 'LIKE', "%$search%");
            })->orwhereHas('property', function ($query) use ($search) {
                $query->where('title', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('mobile', 'LIKE', "%$search%");
            });
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
        foreach ($res as $row) {
            $operate = '&nbsp;<a href="' . route('slider.destroy', $row->id) . '" onclick="return confirmationDelete(event);" class="btn icon btn-danger btn-sm rounded-pill mt-2" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-dark" title="Delete"><i class="bi bi-trash"></i></a>';
            $tempRow['id'] = $row->id;
            $tempRow['category'] =  (!empty($row->category)) ? $row->category->category : '';
            $tempRow['title'] =  (!empty($row->property)) ? $row->property->title : '';
            $tempRow['sequence'] = $row->sequence; //$row->sequence;

            $tempRow['image'] = ($row->image != '') ? '<a class="image-popup-no-margins" href="' . url('') . config('global.IMG_PATH') . config('global.SLIDER_IMG_PATH')  . $row->image . '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . url('') . config('global.IMG_PATH') . config('global.SLIDER_IMG_PATH') . $row->image . '" width="55"></a>' : $row->property->title_image;;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
