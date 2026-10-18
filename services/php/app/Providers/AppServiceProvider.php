<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        foreach (['resend-mail' => [1, 5], 'verify-mail' => [6, 30]] as $name => [$perEmail, $perIp]) {
            RateLimiter::for($name, function (Request $request) use ($perEmail, $perIp): array {
                $email = $request->input('email');

                return [
                    Limit::perMinute($perIp)->by('ip:'.$request->ip()),
                    Limit::perMinute($perEmail)->by('email:'.hash('sha256', is_string($email) ? Str::lower(trim($email)) : '')),
                ];
            });
        }
    }
}
