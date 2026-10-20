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
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleTokenVerifier::class, fn (): GoogleTokenVerifier => new GoogleTokenInfoVerifier(
            (string) config('services.google.client_id'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters used by the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip()),
        ));

        RateLimiter::for('google-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
