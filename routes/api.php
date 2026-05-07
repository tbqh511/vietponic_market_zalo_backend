<?php

use App\Http\Controllers\ZaloApiController;
use Illuminate\Support\Facades\Route;

// ─── Zalo Mini App API – Public ───────────────────────────────────────────────

Route::get('categories', [ZaloApiController::class, 'categories']);
Route::get('products', [ZaloApiController::class, 'products']);
Route::get('banners', [ZaloApiController::class, 'banners']);
Route::get('stations', [ZaloApiController::class, 'stations']);
Route::post('authenticate', [ZaloApiController::class, 'authenticate']);
Route::get('infouser', [ZaloApiController::class, 'zaloapiuser']);
Route::post('get-location', [ZaloApiController::class, 'getLocation']);
Route::post('notify', [ZaloApiController::class, 'notifySDK']);

// ─── Zalo Mini App API – Protected (Customer JWT) ────────────────────────────

Route::group(['middleware' => ['zalo.jwt']], function () {
    Route::post('prepare-order', [ZaloApiController::class, 'prepareOrder']);
    Route::get('orders', [ZaloApiController::class, 'index']);
    Route::get('orders/{id}', [ZaloApiController::class, 'show']);
    Route::post('orders', [ZaloApiController::class, 'store']);
    Route::post('link', [ZaloApiController::class, 'link']);
});

// ─── Zalo Admin API – Protected (X-Admin-Secret header) ──────────────────────

Route::group(['middleware' => ['zalo.admin']], function () {
    Route::patch('orders/{id}/status', [ZaloApiController::class, 'updateStatus']);
});
