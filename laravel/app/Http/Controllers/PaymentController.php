<?php

namespace App\Http\Controllers;

use App\Models\Payments;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    //
    public function index()
    {


        return view('payments.index');
    }
    public function get_payment_list()
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


        $sql = Payments::with('package')->with('customer')->orderBy($sort, $order);





        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->wherehas('customer', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
            })->orwherehas('package', function ($q1) use ($search) {
                $q1->where('name', 'LIKE', "%$search%");
            })->orwhere('transaction_id', 'LIKE', "%$search%")->orwhere('amount', 'LIKE', "%$search%");
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

            $tempRow['customer_name'] = $row->customer->name;
            $tempRow['package_name'] = $row->package->name;
            $tempRow['amount'] = $row->amount;
            $tempRow['transaction_id'] = $row->transaction_id;

            $tempRow['payment_date'] = date('d-m-Y', strtotime($row->created_at));
            $tempRow['payment_gateway'] = $row->payment_gateway;
            $tempRow['status'] = ($row->status == '0') ? '<span class="badge rounded-pill bg-danger">Fail</span>' : '<span class="badge rounded-pill bg-success">Success</span>';

            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
