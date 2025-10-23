<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminController;

Route::post('auth/zalo-exchange', [AuthController::class, 'zaloExchange']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('products', [ProductController::class, 'index']);
Route::get('banners', [BannerController::class, 'index']);
Route::get('stations', [StationController::class, 'index']);

Route::middleware(['jwt.auth'])->group(function () {
    Route::get('user', function (Request $request) { return response()->json(['user' => auth()->user()]); });
    Route::put('user', function (Request $request) { return response()->json(['message' => 'update user placeholder']); });
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);

    Route::middleware(['is_admin'])->prefix('admin')->group(function () {
        Route::get('orders', [AdminController::class, 'orders']);
    });
});
