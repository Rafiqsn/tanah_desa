<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Bidang;
use App\Observers\BidangObserver;

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
        Bidang::observe(BidangObserver::class);
    }
}
