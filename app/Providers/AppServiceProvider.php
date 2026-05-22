<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // 전역 페이지네이션 뷰 (커스텀 컴팩트 디자인)
        Paginator::defaultView('vendor.pagination.drgo');
        Paginator::defaultSimpleView('vendor.pagination.drgo');
    }
}
