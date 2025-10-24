<?php

use App\Http\Controllers\AdvertisementController;

use App\Http\Controllers\AreaMeasurementController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\BedroomController;
use App\Http\Controllers\FrontEndNewsController;
use App\Http\Controllers\FrontEndProductController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PropertController;
use App\Http\Controllers\PropertysInquiryController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\HouseTypeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OutdoorFacilityController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportReasonController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\FrontEndHomeController;
use App\Http\Controllers\FrontEndPropertiesController;
use App\Http\Controllers\FrontEndAgentsController;
use App\Models\Payments;
use App\Models\PropertysInquiry;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


//HuyTBQ: Route for Frontend Page
// Route::get('/', function () {
//     return view('coming_soon');
// });

Route::get('/', [FrontEndHomeController::class, 'index'])->name('index');

//property controller
Route::get('/property/{id}', [FrontEndPropertiesController::class, 'getPropertyById'])->name('property.showid');
Route::get('/autocomplete/street', [FrontEndPropertiesController::class, 'autocompleteStreet'])->name('autocomplete.street');

// Route for displaying the detail of a property
Route::get('/property/detail/{slug}', [FrontEndPropertiesController::class, 'show'])->name('property.show');

// Route for displaying a listing of the properties with search variables
Route::get('/properties', [FrontEndPropertiesController::class, 'index'])->name('properties.index');

//Category menu
Route::get('/nha-ban', [FrontEndPropertiesController::class, 'index']);

Route::get('/dat-ban', [FrontEndPropertiesController::class, 'index']);

//News Layout
Route::get('/new/{id}', [FrontEndNewsController::class,'getNewsById'])->name('new.showid');

Route::get('/news', [FrontEndNewsController::class,'index'])->name('news.index');
//agent layout
Route::get('/agent/{id}', [FrontEndAgentsController::class, 'getAgentById'])->name('agent.showid');


Route::get('/agents', [FrontEndAgentsController::class, 'index'])->name('agents.index');


Route::get('/gioi-thieu',[FrontEndHomeController::class, 'about'])->name('about');

Route::get('/lien-he', function () {
    return view('contact');
});
Route::fallback(function () {
    return view('404');
});

// //Create properties layout
// Route::get('/dang-tin', function () {
//     return view('product_create');
// });
//HuyTBQ: End - Route for Frontend Page


Route::get('/admin', function () {
    return view('auth.login');
});

Route::get('/new-migrate', function () {
    Artisan::call('migrate');
    return redirect()->back();
});


Route::get('/fresh-migrate', function () {
    Artisan::call('migrate:fresh');
    return redirect()->back();
});
Route::get('customer-privacy-policy', [SettingController::class, 'show_privacy_policy'])->name('customer-privacy-policy');


Route::get('customer-terms-conditions', [SettingController::class, 'show_terms_conditions'])->name('customer-terms-conditions');


Auth::routes();

Route::get('privacypolicy', [HomeController::class, 'privacy_policy']);
Route::post('/webhook/razorpay', [WebhookController::class, 'razorpay']);
Route::post('/webhook/paystack', [WebhookController::class, 'paystack']);
Route::post('/webhook/paypal', [WebhookController::class, 'paypal']);
Route::post('/webhook/stripe', [WebhookController::class, 'stripe']);



Route::middleware(['auth', 'checklogin'])->group(function () {
    Route::group(['middleware' => 'language'], function () {

        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('view:cache');


        Route::get('dashboard', [App\Http\Controllers\HomeController::class, 'blank_dashboard'])->name('dashboard');
        Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
        Route::get('about-us', [SettingController::class, 'index']);
        Route::get('privacy-policy', [SettingController::class, 'index']);
        Route::get('terms-conditions', [SettingController::class, 'index']);
        Route::get('system-settings', [SettingController::class, 'index']);
        Route::get('firebase_settings', [SettingController::class, 'index']);
        Route::get('system_version', [SettingController::class, 'index']);
        Route::post('firebase-settings', [SettingController::class, 'firebase_settings']);
        Route::get('system_version', [SettingController::class, 'system_version']);

        Route::post('system_version_setting', [SettingController::class, 'system_version_setting']);

        /// START :: HOME ROUTE
        Route::get('change-password', [App\Http\Controllers\HomeController::class, 'change_password'])->name('changepassword');
        Route::post('check-password', [App\Http\Controllers\HomeController::class, 'check_password'])->name('checkpassword');
        Route::post('store-password', [App\Http\Controllers\HomeController::class, 'store_password'])->name('changepassword.store');
        Route::get('changeprofile', [HomeController::class, 'changeprofile'])->name('changeprofile');
        Route::post('updateprofile', [HomeController::class, 'update_profile'])->name('updateprofile');
        Route::post('firebase_messaging_settings', [HomeController::class, 'firebase_messaging_settings'])->name('firebase_messaging_settings');

        /// END :: HOME ROUTE

        /// START :: SETTINGS ROUTE

        Route::post('settings', [SettingController::class, 'settings']);
        Route::post('set_settings', [SettingController::class, 'system_settings']);
        /// END :: SETTINGS ROUTE

        /// START :: LANGUAGES ROUTE


        Route::resource('language', LanguageController::class);
        Route::get('language_list', [LanguageController::class, 'show']);
        Route::post('language_update', [LanguageController::class, 'update'])->name('language_update');
        Route::get('language-destory/{id}', [LanguageController::class, 'destroy'])->name('language.destroy');
        Route::get('set-language/{lang}', [LanguageController::class, 'set_language']);
        Route::get('downloadPanelFile', [LanguageController::class, 'downloadPanelFile'])->name('downloadPanelFile');
        Route::get('downloadAppFile', [LanguageController::class, 'downloadAppFile'])->name('downloadAppFile');
        /// END :: LANGUAGES ROUTE

        /// START :: PAYMENT ROUTE

        Route::get('getPaymentList', [PaymentController::class, 'get_payment_list']);
        Route::get('payment', [PaymentController::class, 'index']);
        /// END :: PAYMENT ROUTE

        /// START :: USER ROUTE

        Route::resource('users', UserController::class);
        Route::post('users-update', [UserController::class, 'update']);
        Route::post('users-reset-password', [UserController::class, 'resetpassword']);
        Route::get('userList', [UserController::class, 'userList']);

        /// END :: PAYMENT ROUTE

        /// START :: PAYMENT ROUTE

        Route::resource('customer', CustomersController::class);
        Route::get('customerList', [CustomersController::class, 'customerList']);
        Route::post('customerstatus', [CustomersController::class, 'update'])->name('customer.customerstatus');
        /// END :: CUSTOMER ROUTE

        /// START :: SLIDER ROUTE

        Route::resource('slider', SliderController::class);
        Route::post('slider-order', [SliderController::class, 'update'])->name('slider.slider-order');
        Route::get('slider-destory/{id}', [SliderController::class, 'destroy'])->name('slider.destroy');
        Route::get('get-property-by-category', [SliderController::class, 'getPropertyByCategory'])->name('slider.getpropertybycategory');
        Route::get('sliderList', [SliderController::class, 'sliderList']);
        /// END :: SLIDER ROUTE

        /// START :: ARTICLE ROUTE

        Route::resource('article', ArticleController::class);
        Route::get('article_list', [ArticleController::class, 'show']);
        Route::get('article-destory/{id}', [ArticleController::class, 'destroy'])->name('article.destroy');
        /// END :: ARTICLE ROUTE

        /// START :: ADVERTISEMENT ROUTE

        Route::resource('featured_properties', AdvertisementController::class);
        Route::get('featured_properties_list', [AdvertisementController::class, 'show']);
        Route::post('featured_properties-status', [AdvertisementController::class, 'updateStatus'])->name('featured_properties.updateadvertisementstatus');
        Route::post('adv-status-update', [AdvertisementController::class, 'update'])->name('adv-status-update');
        /// END :: ADVERTISEMENT ROUTE

        /// START :: PACKAGE ROUTE

        Route::resource('package', PackageController::class);
        Route::get('package_list', [PackageController::class, 'show']);
        Route::post('package-update', [PackageController::class, 'update']);
        Route::post('package-status', [PackageController::class, 'updatestatus'])->name('package.updatestatus');
        Route::get('get_user_purchased_packages', [PackageController::class, function () {
            return view('packages.users_packages');
        }]);

        Route::get('get_user_package_list', [PackageController::class, 'get_user_package_list']);

        /// END :: PACKAGE ROUTE


        /// START :: CATEGORYW ROUTE

        Route::resource('categories', CategoryController::class);
        Route::get('categoriesList', [CategoryController::class, 'categoryList']);
        Route::post('categories-update', [CategoryController::class, 'update']);
        Route::post('categories-status', [CategoryController::class, 'updateCategory'])->name('customer.categoriesstatus');
        /// END :: CATEGORYW ROUTE


        /// START :: PARAMETER FACILITY ROUTE

        Route::resource('parameters', ParameterController::class);
        Route::get('parameter-list', [ParameterController::class, 'show']);
        Route::post('parameter-update', [ParameterController::class, 'update']);

        /// END :: PARAMETER FACILITY ROUTE

        /// START :: OUTDOOR FACILITY ROUTE

        Route::resource('outdoor_facilities', OutdoorFacilityController::class);
        Route::get('facility-list', [OutdoorFacilityController::class, 'show']);
        Route::post('facility-update', [OutdoorFacilityController::class, 'update']);
        Route::get('facility-delete/{id}', [OutdoorFacilityController::class, 'destroy'])->name('outdoor_facilities.destroy');
        /// END :: OUTDOOR FACILITY ROUTE


        /// START :: PROPERTY ROUTE
        Route::resource('property', PropertController::class);
        Route::get('getPropertyList', [PropertController::class, 'getPropertyList']);
        Route::post('property-status', [PropertController::class, 'updateStatus'])->name('property.updatepropertystatus');
        Route::post('property-gallery', [PropertController::class, 'removeGalleryImage'])->name('property.removeGalleryImage');
        Route::get('get-state-by-country', [PropertController::class, 'getStatesByCountry'])->name('property.getStatesByCountry');
        Route::get('property-destory/{id}', [PropertController::class, 'destroy'])->name('property.destroy');

        Route::get('updateFCMID', [UserController::class, 'updateFCMID']);
        /// END :: PROPERTY ROUTE


        /// START :: PROPERTY INQUIRY
        Route::resource('property-inquiry', PropertysInquiryController::class);
        Route::get('getPropertyInquiryList', [PropertysInquiryController::class, 'getPropertyInquiryList']);
        Route::post('property-inquiry-status', [PropertysInquiryController::class, 'updateStatus'])->name('property-inquiry.updateStatus');

        /// ENND :: PROPERTY INQUIRY
        /// START :: REPORTREASON
        Route::resource('report-reasons', ReportReasonController::class);
        Route::get('report-reasons-list', [ReportReasonController::class, 'show']);
        Route::post('report-reasons-update', [ReportReasonController::class, 'update']);
        Route::get('report-reasons-destroy/{id}', [ReportReasonController::class, 'destroy'])->name('reasons.destroy');

        Route::get('users_reports', [ReportReasonController::class, 'users_reports']);

        Route::get('user_reports_list', [ReportReasonController::class, 'user_reports_list']);








        /// END :: REPORTREASON





        Route::resource('property-inquiry', PropertysInquiryController::class);


        /// START :: CHAT ROUTE

        Route::get('getChatList', [ChatController::class, 'getChats']);
        Route::post('store_chat', [ChatController::class, 'store']);
        Route::get('getAllMessage', [ChatController::class, 'getAllMessage']);
        /// END :: CHAT ROUTE


        /// START :: NOTIFICATION
        Route::resource('notification', NotificationController::class);
        Route::get('notificationList', [NotificationController::class, 'notificationList']);
        Route::get('notification-delete', [NotificationController::class, 'destroy']);
        Route::post('notification-multiple-delete', [NotificationController::class, 'multiple_delete']);
        /// END :: NOTIFICATION

        Route::get('chat', function () {
            return view('chat');
        });

        Route::get('calculator', function () {
            return view('Calculator.calculator');
        });
    });
});

Auth::routes();
