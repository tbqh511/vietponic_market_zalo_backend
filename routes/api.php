<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\ViettelPostWebhookController;
use App\Http\Controllers\ZaloApiController;
use App\Http\Controllers\Admin\StockApiController;
use App\Http\Controllers\Farm\FarmStockController;
use Illuminate\Support\Facades\Route;

// ─── Locations (public — chỉ danh mục, không cần auth) ───────────────────────

Route::prefix('locations')->group(function () {
    Route::get('provinces', [ShippingController::class, 'provinces']);
    Route::get('districts', [ShippingController::class, 'districts']);
    Route::get('wards', [ShippingController::class, 'wards']);
});

// ─── Zalo Mini App API – Public ───────────────────────────────────────────────

Route::get('categories', [ZaloApiController::class, 'categories']);
Route::get('products', [ZaloApiController::class, 'products']);
Route::get('banners', [ZaloApiController::class, 'banners']);
Route::get('stations', [ZaloApiController::class, 'stations']);
Route::post('authenticate', [ZaloApiController::class, 'authenticate']);
Route::get('infouser', [ZaloApiController::class, 'zaloapiuser']);
Route::post('get-location', [ZaloApiController::class, 'getLocation']);
Route::post('notify', [ZaloApiController::class, 'notifySDK']);

// ─── ViettelPost webhook (public — verify token + IP whitelist trong controller) ─
Route::post('viettelpost/webhook', [ViettelPostWebhookController::class, 'handle']);

// ─── Zalo Mini App API – Protected (Customer JWT) ────────────────────────────

Route::group(['middleware' => ['zalo.jwt']], function () {
    Route::post('prepare-order', [ZaloApiController::class, 'prepareOrder']);
    Route::get('orders', [ZaloApiController::class, 'index']);
    Route::get('orders/{id}', [ZaloApiController::class, 'show']);
    Route::post('orders', [ZaloApiController::class, 'store']);
    Route::post('create-order', [ZaloApiController::class, 'store']);
    Route::post('checkout', [ZaloApiController::class, 'store']);
    Route::post('orders/{id}/cancel', [ZaloApiController::class, 'cancelByCustomer']);
    Route::post('link', [ZaloApiController::class, 'link']);

    // ─── Affiliate (customer-facing) ─────────────────────────────────────
    Route::post('affiliate/register', [AffiliateController::class, 'register']);
    Route::get('affiliate/me', [AffiliateController::class, 'me']);
    Route::patch('affiliate/bank', [AffiliateController::class, 'updateBank']);
    Route::get('affiliate/commissions', [AffiliateController::class, 'commissions']);
    Route::get('affiliate/referrals', [AffiliateController::class, 'referrals']);
    Route::post('affiliate/apply-referral', [AffiliateController::class, 'applyReferral']);

    // ─── Shipping estimate (rate-limited: 60 req/phút) ────────────────────
    Route::middleware('throttle:60,1')
        ->post('shipping/estimate', [ShippingController::class, 'estimate']);
});

// ─── Farm Partner API – Protected (JWT + farm_partner role) ──────────────────

Route::group(['prefix' => 'farm', 'middleware' => ['zalo.farm']], function () {
    Route::get('inventory', [FarmStockController::class, 'index']);
    Route::get('inventory/{id}/movements', [FarmStockController::class, 'movements']);
    Route::post('inventory/{id}/import', [FarmStockController::class, 'import']);
    Route::post('inventory/{id}/export', [FarmStockController::class, 'export']);
});

// ─── Zalo Admin API – Protected (X-Admin-Secret header) ──────────────────────

Route::group(['middleware' => ['zalo.admin']], function () {
    Route::patch('orders/{id}/status', [ZaloApiController::class, 'updateStatus']);
    Route::post('orders/{id}/refund/confirm-manual', [ZaloApiController::class, 'confirmManualRefund']);

    // ─── Inventory Admin API ──────────────────────────────────────────────────
    Route::get('admin/inventory', [StockApiController::class, 'index']);
    Route::get('admin/inventory/low-stock', [StockApiController::class, 'lowStock']);
    Route::get('admin/inventory/{id}/movements', [StockApiController::class, 'movements']);
    Route::post('admin/inventory/{id}/import', [StockApiController::class, 'import']);
    Route::post('admin/inventory/{id}/adjust', [StockApiController::class, 'adjust']);
});
