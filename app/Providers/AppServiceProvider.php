<?php

namespace App\Providers;

use App\Models\ZaloOrder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        // Bind counter cho sidebar badge "Hoàn tiền chờ xử lý".
        // Cache 60s để tránh hammer DB (admin có thể mở nhiều tab/refresh nhanh).
        // Cache invalidate trong RefundController::confirm() sau khi xử lý 1 đơn.
        View::composer('layouts.sidebar', function ($view) {
            $count = Cache::remember(
                'admin.refunds.pending_count',
                60,
                fn () => ZaloOrder::where('refund_status', 'pending_manual')->count()
            );
            $view->with('pendingRefundCount', $count);
        });
    }
}
