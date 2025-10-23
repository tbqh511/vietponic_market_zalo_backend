<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Station;

class StationController extends Controller
{
    public function index(Request $request)
    {
        $stations = Station::all();
        return response()->json($stations);
    }
}
