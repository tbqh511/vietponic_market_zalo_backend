<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;


class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $articles = Article::all();



        return view('article.index', ['articles' => $articles]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $category = Category::all();
        return view('article.create', ['category' => $category]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        if (!has_permissions('read', 'property')) {
            return redirect()->back()->with('error', env('PERMISSION_ERROR_MSG'));
        } else {
            $request->validate([

                'image.*' => 'required|image|mimes:jpg,png,jpeg|max:2048',
            ]);

            $destinationPath = public_path('images') . config('global.ARTICLE_IMG_PATH');
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $article = new Article();
            $article->title = $request->title;
            $article->description = $request->description;


            if ($request->hasFile('image')) {

                $profile = $request->file('image');
                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                $profile->move($destinationPath, $imageName);
                $article->image = $imageName;
            } else {
                $article->image  = '';
            }

            $article->save();
            return back()->with('success', 'Successfully Added');
        }
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

        $sql = Article::orderBy($sort, $order);


        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('description', 'LIKE', "%$search%");
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

            if (has_permissions('update', 'property')) {
                $operate = '<a  href="' . route('article.edit', $row->id) . '"  class="btn icon btn-primary btn-sm rounded-pill mt-2" title="Edit"><i class="fa fa-edit"></i></a>';
            }
            if (has_permissions('delete', 'property')) {
                $operate .= '&nbsp;&nbsp;<a href="' . route('article.destroy', $row->id) . '" onclick="return confirmationDelete(event);" class="btn icon btn-danger btn-sm rounded-pill mt-2" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-dark" title="Delete"><i class="bi bi-trash"></i></a>';
            }

            $tempRow['id'] = $row->id;
            $tempRow['title'] = $row->title;
            $tempRow['description'] = substr($row->description, 0, 150) . '...';
            $tempRow['image'] = ($row->image != '') ? '<a class="image-popup-no-margins" href="' . $row->image . '"><img class="rounded avatar-md shadow img-fluid" alt="" src="' . ($row->image) . '" width="55"></a>' : '';
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

        $list = Article::where('id', $id)->get()->first();
        $category = Category::all();
        return view('article.edit', compact('list', 'id', 'category'));
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

        $destinationPath = public_path('images') . config('global.ARTICLE_IMG_PATH');
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $updatearticle = Article::find($id);
        if ($request->hasFile('image')) {
            $profile = $request->file('image');
            $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
            $profile->move($destinationPath, $imageName);
            $updatearticle->image = $imageName;

            if ($updatearticle->image != '') {
                if (file_exists(public_path('images') . config('global.ARTICLE_IMG_PATH') .  $updatearticle->image)) {
                    unlink(public_path('images') . config('global.ARTICLE_IMG_PATH') . $updatearticle->image);
                }
            }
        }
        $updatearticle->title = $request->title;
        $updatearticle->description = $request->description;



        $updatearticle->update();
        return back()->with('success', 'Successfully Update');
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
        if (!has_permissions('delete', 'property')) {
            return redirect()->back()->with('error', env('PERMISSION_ERROR_MSG'));
        } else {
            $article = Article::find($id);
            if ($article->delete()) {


                if ($article->image != '') {
                    echo "in 1 if";
                    if (file_exists(public_path('images') . config('global.ARTICLE_IMG_PATH') . $article->image)) {
                        unlink(public_path('images') . config('global.ARTICLE_IMG_PATH') . $article->image);
                    }
                }

                // Notifications::where('articles_id', $id)->delete();
                return back()->with('success', 'Article Deleted Successfully');
            } else {
                return back()->with('error', 'Something Wrong');
            }
        }
    }
}
