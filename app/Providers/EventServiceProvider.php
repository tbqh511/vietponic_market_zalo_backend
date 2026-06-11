<?php

namespace App\Providers;

use App\Events\OrderDelivered;
use App\Events\OrderPaymentSucceeded;
use App\Listeners\CreateVtpOrderOnPayment;
use App\Listeners\DeductStockOnPayment;
use App\Listeners\RecordAffiliateCommission;
use App\Listeners\SendOrderNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        OrderPaymentSucceeded::class => [
            DeductStockOnPayment::class,
            CreateVtpOrderOnPayment::class,
            SendOrderNotification::class,
        ],
        // AFF-03 (B2): hoa hồng CTV tính theo đơn GIAO THÀNH CÔNG, không theo
        // thanh toán → RecordAffiliateCommission nghe OrderDelivered (gồm cả COD).
        OrderDelivered::class => [
            RecordAffiliateCommission::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
