<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\ZaloApiController;
use App\Http\Controllers\Admin\StockApiController;
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
    Route::post('create-order', [ZaloApiController::class, 'store']);
    Route::post('checkout', [ZaloApiController::class, 'store']);
    Route::post('link', [ZaloApiController::class, 'link']);

    // ─── Affiliate (customer-facing) ─────────────────────────────────────
    Route::post('affiliate/register', [AffiliateController::class, 'register']);
    Route::get('affiliate/me', [AffiliateController::class, 'me']);
    Route::patch('affiliate/bank', [AffiliateController::class, 'updateBank']);
    Route::get('affiliate/commissions', [AffiliateController::class, 'commissions']);
    Route::get('affiliate/referrals', [AffiliateController::class, 'referrals']);
    Route::post('affiliate/apply-referral', [AffiliateController::class, 'applyReferral']);
});

// ─── Zalo Admin API – Protected (X-Admin-Secret header) ──────────────────────

Route::group(['middleware' => ['zalo.admin']], function () {
    Route::patch('orders/{id}/status', [ZaloApiController::class, 'updateStatus']);

    // ─── Inventory Admin API ──────────────────────────────────────────────────
    Route::get('admin/inventory', [StockApiController::class, 'index']);
    Route::get('admin/inventory/low-stock', [StockApiController::class, 'lowStock']);
    Route::get('admin/inventory/{id}/movements', [StockApiController::class, 'movements']);
    Route::post('admin/inventory/{id}/import', [StockApiController::class, 'import']);
    Route::post('admin/inventory/{id}/adjust', [StockApiController::class, 'adjust']);
});
