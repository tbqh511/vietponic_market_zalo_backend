<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FrontEndHomeController;
use App\Http\Controllers\FrontEndPropertiesController;
use App\Http\Controllers\Admin\ZaloCategoryController;
use App\Http\Controllers\Admin\ZaloProductController;
use App\Http\Controllers\Admin\ZaloOrderController;
use App\Http\Controllers\Admin\ZaloOrderItemController;
use App\Http\Controllers\Admin\ZaloStationController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\AffiliatePartnerController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\ZaloCustomerController;
use App\Http\Controllers\Admin\FarmController;
use App\Http\Controllers\Admin\FarmPayoutController;
use Illuminate\Support\Facades\Artisan;

// ─── Frontend / Landing Page ──────────────────────────────────────────────────

Route::get('/', [FrontEndHomeController::class, 'index'])->middleware('coming.soon')->name('index');

Route::get('/property/{id}', [FrontEndPropertiesController::class, 'getPropertyById'])->name('property.showid');
Route::get('/autocomplete/street', [FrontEndPropertiesController::class, 'autocompleteStreet'])->name('autocomplete.street');
Route::get('/property/detail/{slug}', [FrontEndPropertiesController::class, 'show'])->name('property.show');
Route::get('/properties', [FrontEndPropertiesController::class, 'index'])->name('properties.index');
Route::get('/nha-ban', [FrontEndPropertiesController::class, 'index']);
Route::get('/dat-ban', [FrontEndPropertiesController::class, 'index']);

Route::get('/gioi-thieu', [FrontEndHomeController::class, 'about'])->name('about');
Route::get('/lien-he', function () {
    return view('contact');
});

Route::fallback(function () {
    return view('404');
});

// ─── Public Pages ─────────────────────────────────────────────────────────────

Route::get('/admin', function () {
    return view('auth.login');
});

Route::get('customer-privacy-policy', [SettingController::class, 'show_privacy_policy'])->name('customer-privacy-policy');
Route::get('customer-terms-conditions', [SettingController::class, 'show_terms_conditions'])->name('customer-terms-conditions');
Route::get('privacypolicy', [HomeController::class, 'privacy_policy']);

Auth::routes();

// ─── Admin Panel (authenticated) ──────────────────────────────────────────────

Route::middleware(['auth', 'checklogin'])->group(function () {
    Route::group(['middleware' => 'language'], function () {

        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('view:cache');

        Route::get('dashboard', [HomeController::class, 'blank_dashboard'])->name('dashboard');
        Route::get('/home', [HomeController::class, 'index'])->name('home');

        // Settings
        Route::get('about-us', [SettingController::class, 'index']);
        Route::get('privacy-policy', [SettingController::class, 'index']);
        Route::get('terms-conditions', [SettingController::class, 'index']);
        Route::get('system-settings', [SettingController::class, 'index']);
        Route::get('firebase_settings', [SettingController::class, 'index']);
        Route::get('system_version', [SettingController::class, 'system_version']);
        Route::post('firebase-settings', [SettingController::class, 'firebase_settings']);
        Route::post('system_version_setting', [SettingController::class, 'system_version_setting']);
        Route::post('settings', [SettingController::class, 'settings']);
        Route::post('set_settings', [SettingController::class, 'system_settings']);

        // Profile & password
        Route::get('change-password', [HomeController::class, 'change_password'])->name('changepassword');
        Route::post('check-password', [HomeController::class, 'check_password'])->name('checkpassword');
        Route::post('store-password', [HomeController::class, 'store_password'])->name('changepassword.store');
        Route::get('changeprofile', [HomeController::class, 'changeprofile'])->name('changeprofile');
        Route::post('updateprofile', [HomeController::class, 'update_profile'])->name('updateprofile');
        Route::post('firebase_messaging_settings', [HomeController::class, 'firebase_messaging_settings'])->name('firebase_messaging_settings');

        // Languages
        Route::resource('language', LanguageController::class);
        Route::get('language_list', [LanguageController::class, 'show']);
        Route::post('language_update', [LanguageController::class, 'update'])->name('language_update');
        Route::get('language-destory/{id}', [LanguageController::class, 'destroy'])->name('language.destroy');
        Route::get('set-language/{lang}', [LanguageController::class, 'set_language']);
        Route::get('downloadPanelFile', [LanguageController::class, 'downloadPanelFile'])->name('downloadPanelFile');
        Route::get('downloadAppFile', [LanguageController::class, 'downloadAppFile'])->name('downloadAppFile');

        // Users management
        Route::resource('users', UserController::class);
        Route::post('users-update', [UserController::class, 'update']);
        Route::post('users-reset-password', [UserController::class, 'resetpassword']);
        Route::get('userList', [UserController::class, 'userList']);

        // ─── Zalo Management ──────────────────────────────────────────────────

        Route::resource('zalo-categories', ZaloCategoryController::class);
        Route::resource('zalo-products', ZaloProductController::class);
        Route::resource('zalo-orders', ZaloOrderController::class);
        Route::post('zalo-orders/{order}/vtp-retry', [ZaloOrderController::class, 'retryVtp'])->name('zalo-orders.vtp-retry');
        Route::resource('zalo-stations', ZaloStationController::class);
        Route::resource('banners', BannerController::class);
        Route::get('zalo-orders/{order}/items/create', [ZaloOrderItemController::class, 'create'])->name('zalo-order-items.create');
        Route::resource('zalo-order-items', ZaloOrderItemController::class)->except(['index']);

        // ─── Inventory Management ─────────────────────────────────────────────
        Route::get('inventory', [StockController::class, 'index'])->name('inventory.index');
        Route::get('inventory/low-stock', [StockController::class, 'lowStock'])->name('inventory.low-stock');
        Route::get('inventory/report', [StockController::class, 'report'])->name('inventory.report');
        Route::get('inventory/{inventory}', [StockController::class, 'show'])->name('inventory.show');
        Route::get('inventory/{inventory}/import', [StockController::class, 'importForm'])->name('inventory.import');
        Route::post('inventory/{inventory}/import', [StockController::class, 'importStore'])->name('inventory.import.store');
        Route::post('inventory/{inventory}/adjust', [StockController::class, 'adjust'])->name('inventory.adjust');
        Route::post('inventory/{inventory}/quick-export', [StockController::class, 'quickExport'])->name('inventory.quick-export');
        Route::post('inventory/{inventory}/reorder-point', [StockController::class, 'reorderPoint'])->name('inventory.reorder-point');

        // ─── Farm Partner Hub ─────────────────────────────────────────────────
        // Giai đoạn 1: Yêu cầu đăng ký (lọc customers có farm_partner_status='requested')
        Route::get('farm-requests', [FarmController::class, 'requestsIndex'])->name('farm-requests.index');
        Route::post('farm-requests/{customer}/approve', [FarmController::class, 'approveRequest'])->name('farm-requests.approve');
        Route::post('farm-requests/{customer}/reject', [FarmController::class, 'rejectRequest'])->name('farm-requests.reject');

        // Giai đoạn 2: Quản lý Farm — không expose create/store, farm chỉ tạo khi duyệt yêu cầu
        Route::resource('farms', FarmController::class)->except(['create', 'store']);
        Route::patch('farms/{farm}/suspend', [FarmController::class, 'suspend'])->name('farms.suspend');
        Route::patch('farms/{farm}/reactivate', [FarmController::class, 'reactivate'])->name('farms.reactivate');
        // Tab "Cấu hình Sản phẩm"
        Route::post('farms/{farm}/products', [FarmController::class, 'attachProduct'])->name('farms.products.attach');
        Route::delete('farms/{farm}/products/{product}', [FarmController::class, 'detachProduct'])->name('farms.products.detach');

        // Giai đoạn 3: Đối soát (Payout) — không dùng Route::resource vì có flow riêng (mark-paid, cancel)
        Route::get('farm-payouts', [FarmPayoutController::class, 'index'])->name('farm-payouts.index');
        Route::get('farm-payouts/create', [FarmPayoutController::class, 'create'])->name('farm-payouts.create');
        Route::post('farm-payouts', [FarmPayoutController::class, 'store'])->name('farm-payouts.store');
        Route::get('farm-payouts/{payout}', [FarmPayoutController::class, 'show'])->name('farm-payouts.show');
        Route::post('farm-payouts/{payout}/mark-paid', [FarmPayoutController::class, 'markPaid'])->name('farm-payouts.mark-paid');
        Route::post('farm-payouts/{payout}/cancel', [FarmPayoutController::class, 'cancel'])->name('farm-payouts.cancel');

        // ─── Customer Management ──────────────────────────────────────────────
        Route::resource('zalo-customers', ZaloCustomerController::class)->except(['create', 'store']);
        Route::patch('zalo-customers/{id}/toggle-active', [ZaloCustomerController::class, 'toggleActive'])->name('zalo-customers.toggle-active');
        Route::patch('zalo-customers/{id}/promote-farm', [ZaloCustomerController::class, 'promoteFarmPartner'])->name('zalo-customers.promote-farm');
        Route::patch('zalo-customers/{id}/suspend-farm', [ZaloCustomerController::class, 'suspendFarmPartner'])->name('zalo-customers.suspend-farm');

        // ─── Affiliate Management ─────────────────────────────────────────────
        Route::resource('affiliate-partners', AffiliatePartnerController::class)->except(['create', 'store']);
        Route::patch('affiliate-partners/{id}/status', [AffiliatePartnerController::class, 'updateStatus'])->name('affiliate-partners.status');
        Route::post('affiliate-partners/{id}/payouts', [AffiliatePartnerController::class, 'createPayout'])->name('affiliate-partners.payouts.create');
        Route::patch('affiliate-settings/commission-rate', [AffiliatePartnerController::class, 'updateCommissionRate'])->name('affiliate-settings.commission-rate');
        Route::patch('affiliate-settings/auto-approve', [AffiliatePartnerController::class, 'toggleAutoApprove'])->name('affiliate-settings.auto-approve');
        Route::patch('affiliate-settings/enabled', [AffiliatePartnerController::class, 'toggleEnabled'])->name('affiliate-settings.enabled');
    });
});

Auth::routes();
