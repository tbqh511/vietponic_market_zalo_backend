<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Farm;
use App\Models\FarmStockBatch;
use App\Models\User;
use App\Models\ZaloOrder;
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
            $tz = 'Asia/Ho_Chi_Minh';
            $today = now()->timezone($tz)->toDateString();
            $monthStart = now()->timezone($tz)->startOfMonth();

            // KPI Row 1
            $stats['month_revenue']   = ZaloOrder::where('status', 'delivered')
                ->where('delivered_at', '>=', $monthStart)->sum('total') ?? 0;
            $stats['today_orders']    = ZaloOrder::whereDate('created_at', $today)->count();
            $stats['total_customers'] = Customer::count();
            $stats['active_farms']    = Farm::where('is_active', true)->count();

            // Alert Row 2
            $stats['pending_orders']        = ZaloOrder::where('status', 'pending')->count();
            $stats['pending_farm_requests'] = Customer::where('farm_partner_status', 'requested')->count();
            $stats['expiring_soon']         = FarmStockBatch::where('status', 'active')
                ->where('quantity_remaining', '>', 0)
                ->whereNotNull('expire_date')
                ->whereDate('expire_date', '<=', now()->addDays(7))->count();
            $stats['new_customers_month']   = Customer::where('created_at', '>=', $monthStart)->count();

            // Revenue + orders chart — last 30 days (timezone-aware)
            $revenueRows = ZaloOrder::where('status', 'delivered')
                ->where('delivered_at', '>=', now()->subDays(29))
                ->selectRaw("DATE(CONVERT_TZ(delivered_at,'+00:00','+07:00')) as date, SUM(total) as revenue, COUNT(*) as cnt")
                ->groupBy('date')->orderBy('date')->get();

            // Order status pie chart
            $orderStatusBreakdown = ZaloOrder::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')->get();

            // Recent orders table
            $recentOrders = ZaloOrder::with('customer')->latest('created_at')->take(10)->get();

            // Expiring batches table
            $expiringBatches = FarmStockBatch::with(['product', 'farm'])
                ->where('status', 'active')
                ->where('quantity_remaining', '>', 0)
                ->whereNotNull('expire_date')
                ->orderBy('expire_date')
                ->take(8)->get();

            $settings['company_name']    = system_setting('company_name');
            $settings['currency_symbol'] = system_setting('currency_symbol');

            return view('home', compact(
                'stats', 'settings', 'revenueRows',
                'orderStatusBreakdown', 'recentOrders', 'expiringBatches'
            ));
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

