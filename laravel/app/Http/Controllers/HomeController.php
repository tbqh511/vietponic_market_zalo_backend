<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Property;
use App\Models\PropertysInquiry;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {


        if (!has_permissions('read', 'dashboard')) {
            return redirect('dashboard')->with('error', PERMISSION_ERROR_MSG);
        } else {


            $properties = Property::select('id', 'title', 'price', 'title_image', 'latitude', 'longitude', 'city')->orderBy('price', 'DESC')->limit(10)->get();
            $properties_data = Property::select('id', 'title', 'price', 'title_image', 'latitude', 'longitude', 'city', 'total_click')->where('total_click','>',0)->orderBy('total_click', 'DESC')->limit(10)->get();


            // 0:Sell 1:Rent 2:Sold 3:Rented
            $list['total_sell_property'] = Property::where('propery_type', '0')->get()->count();
            $list['total_rant_property'] = Property::where('propery_type', '1')->get()->count();


            $list['total_property_inquiry'] = PropertysInquiry::all()->count();
            $list['total_properties'] = Property::all()->count();
            $list['total_articles'] = Article::all()->count();


            $list['total_categories'] = Category::all()->count();



            $list['total_customer'] = Customer::all()->count();
            $list['recent_properties'] = Property::orderBy('id', 'DESC')->limit(10)->where('status',1)->get();
            $property = Property::select(DB::raw("COUNT(*) as count"), DB::raw("DATE_FORMAT(created_at, '%M %Y') as month"))
                ->whereYear('created_at', date('Y'))
                ->groupBy(DB::raw("YEAR(created_at)"), DB::raw("MONTH(created_at)"))
                ->pluck('count', 'month');




            // dd(DB::getQueryLog());
            $property_labels = $property->keys();
            $property_data = $property->values();
            // if (count($result)) {

            $rows = array();
            $firebase_settings = array();



            $operate = '';

            $settings['company_name'] = system_setting('company_name');
            $settings['currency_symbol'] = system_setting('currency_symbol');


            // }
            $userData = Customer::select(\DB::raw("COUNT(*) as count"))
                ->whereYear('created_at', date('Y'))
                ->groupBy(\DB::raw("Month(created_at)"))
                ->pluck('count');

            return view('home', compact('list', 'settings', 'property_labels', 'property_data', 'properties', 'userData', 'properties_data'));
        }
    }
    public function blank_dashboard()
    {


        return view('blank_home');
    }


    public function change_password()
    {

        return view('change_password.index');
    }
    public function changeprofile()
    {
        return view('change_profile.index');
    }

    public function check_password(Request $request)
    {
        $id = Auth::id();
        $oldpassword = $request->old_password;
        $user = DB::table('users')->where('id', $id)->first();


        $response['error'] = password_verify($oldpassword, $user->password) ? true : false;
        return response()->json($response);
        // return (password_verify($oldpassword, $user->password)) ? true : false;
    }
    // return Hash::check('admin123', '$2y$10$EqdAtOfZowAvBGInra6aXe6burYrC/c6mbgJ1DnqV/0Myo6sTT6Aq') ? true : false;


    public function store_password(Request $request)
    {

        $confPassword = $request->confPassword;
        $id = Auth::id();
        $role = Auth::user()->type;

        $users = User::find($id);
        // if ($role == 0) {
        //     $users->name  = $request->name;
        //     $users->email  = $request->email;
        // }

        if (isset($confPassword) && $confPassword != '') {
            $users->password = Hash::make($confPassword);
        }

        $users->update();
        return back()->with('success', 'Password Change Successfully');
    }
    function update_profile(Request $request)
    {
        $id = Auth::id();
        $role = Auth::user()->type;

        $users = User::find($id);
        if ($role == 0) {
            $users->name  = $request->name;
            $users->email  = $request->email;
        }
        $users->update();
        return back()->with('success', 'Profile Updated Successfully');
    }

    public function privacy_policy()
    {
        echo system_setting('privacy_policy');
    }


    public function firebase_messaging_settings(Request $request)
    {
        $file_path = public_path('firebase-messaging-sw.js');

        // Check if file exists
        if (File::exists($file_path)) {
            // Remove existing file
            File::delete($file_path);
        }

        // Move new file
        $request->file->move(public_path(), 'firebase-messaging-sw.js');
    }
}

