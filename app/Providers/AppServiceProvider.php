<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        /*
         * /live/* dibuka lewat tunnel ke internet publik -- lonjakan
         * penonton (atau siapa pun yang menemukan URL-nya) tidak boleh bisa
         * membebani mesin scoring yang sama juga dipakai gelanggang.
         * Per-IP, bukan per-user, karena rute ini tidak butuh login sama
         * sekali.
         */
        RateLimiter::for('live', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
