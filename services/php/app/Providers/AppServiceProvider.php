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
        RateLimiter::for('resend-email', function (Request $request) {
            return Limit::perMinute(3)->by($this->emailRateLimitKey($request));
        });

        RateLimiter::for('verify-email', function (Request $request) {
            return Limit::perMinute(5)->by($this->emailRateLimitKey($request));
        });
    }

    private function emailRateLimitKey(Request $request): string
    {
        return hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip());
    }
}
