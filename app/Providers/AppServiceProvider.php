<?php

namespace App\Providers;

use App\Auth\GoogleTokenInfoVerifier;
use App\Auth\GoogleTokenVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleTokenVerifier::class, fn (): GoogleTokenVerifier => new GoogleTokenInfoVerifier(
            (string) config('services.google.client_id'),
        ));
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip()),
        ));

        RateLimiter::for('google-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('password-email', fn (Request $request) => Limit::perMinute(3)->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip()),
        ));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('verification-email', fn (Request $request) => Limit::perMinute(3)->by(
            (string) ($request->user()?->getKey() ?? $request->ip()),
        ));
    }
}
