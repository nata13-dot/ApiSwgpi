<?php

namespace App\Providers;

use App\Support\CareerContext;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CareerContext::class, fn () => new CareerContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identity = mb_strtolower((string) ($request->input('id') ?? $request->input('email') ?? 'unknown'));
            return Limit::perMinute(5)->by($request->ip().'|'.$identity);
        });

        RateLimiter::for('password-recovery', function (Request $request) {
            $identity = mb_strtolower((string) ($request->input('email') ?? $request->input('correo') ?? 'unknown'));
            return Limit::perMinute(3)->by($request->ip().'|'.$identity);
        });
    }
}
