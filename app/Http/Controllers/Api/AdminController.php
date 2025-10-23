<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function orders(Request $request)
    {
        // Placeholder: return all orders from mock
        $mockPath = storage_path('app/mock/orders.json');
        if (file_exists($mockPath)) {
            return response()->json(json_decode(file_get_contents($mockPath), true));
        }
        return response()->json([]);
    }
}
