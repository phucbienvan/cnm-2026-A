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
        RateLimiter::for('resend-email', function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(3)->by('resend-ip:'.$request->ip()),
                Limit::perMinute(1)->by('resend-email:'.hash('sha256', $email)),
            ];
        });
    }
}
