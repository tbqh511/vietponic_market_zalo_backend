<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\ViettelPostWebhookController;
use App\Http\Controllers\VoucherApiController;
use App\Http\Controllers\ZaloApiController;
use App\Http\Controllers\Admin\StockApiController;
use App\Http\Controllers\Farm\FarmHubController;
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

    // ─── Voucher / mã giảm giá (customer-facing) ──────────────────────────
    Route::get('vouchers/available', [VoucherApiController::class, 'available']);
    Route::post('vouchers/validate', [VoucherApiController::class, 'validateCode']);
});

// ─── Farm Partnership Request — customer JWT (chưa cần là farm partner) ──────
// Endpoint cho customer thường xin trở thành farm partner. Nằm ngoài group
// zalo.farm vì người gọi chưa được duyệt — middleware farm sẽ chặn họ.
Route::group(['middleware' => ['zalo.jwt']], function () {
    Route::post('farm/request-partnership', [FarmHubController::class, 'requestPartnership']);
});

// ─── Farm Partner API – Protected (JWT + farm_partner role) ──────────────────

Route::group(['prefix' => 'farm', 'middleware' => ['zalo.farm']], function () {
    // Farm Partner Hub — quản lý batch tồn kho (đổi sang batch model).
    Route::get('inventory', [FarmStockController::class, 'index']);
    Route::get('inventory/{id}/movements', [FarmStockController::class, 'movements']);
    // POST /inventory/import (body chứa product_id) — không còn nhận {id} trên URL.
    Route::post('inventory/import', [FarmStockController::class, 'import']);
    // Đóng/recall/expire batch.
    Route::post('inventory/{id}/close', [FarmStockController::class, 'close']);

    // ─── Farm Hub dashboard aliases (flat URL theo spec FE) ─────────────────
    // Map sang methods sẵn có trong FarmHubController. Đặt trước group /hub
    // để FE có thể chọn URL phẳng (/farm/dashboard) hoặc nested (/farm/hub/overview).
    Route::get('me', [FarmHubController::class, 'profile']);
    Route::get('dashboard', [FarmHubController::class, 'overview']);
    Route::get('analytics', [FarmHubController::class, 'analytics']);
    Route::get('products/today', [FarmHubController::class, 'productsToday']);
    Route::get('orders/incoming', [FarmHubController::class, 'incomingOrders']);
    Route::get('payouts', [FarmHubController::class, 'payouts']);
    // Alias write: /farm/stock-in trỏ thẳng FarmStockController@import.
    Route::post('stock-in', [FarmStockController::class, 'import']);

    // ─── Farm Hub dashboard (nested, read-only) ──────────────────────────
    // Tách prefix /hub để FE phân biệt dashboard (read) với inventory (write).
    Route::prefix('hub')->group(function () {
        Route::get('profile', [FarmHubController::class, 'profile']);
        Route::get('overview', [FarmHubController::class, 'overview']);
        Route::get('revenue', [FarmHubController::class, 'revenue']);
        Route::get('top-products', [FarmHubController::class, 'topProducts']);
        Route::get('inventory', [FarmHubController::class, 'inventory']);
    });
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
