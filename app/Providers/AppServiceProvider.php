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
        // Una instancia por petición: guarda en memoria el estado de requisitos de cada proyecto.
        $this->app->scoped(\App\Services\EvaluationRequirementsService::class);
        $this->app->scoped(CareerContext::class, fn () => new CareerContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Micro API de correo (Mailer_Swgpi): MAIL_MAILER=swgpi, SWGPI_MAILER_URL y SWGPI_MAILER_KEY.
        \Illuminate\Support\Facades\Mail::extend('swgpi', fn (array $config) => new \App\Mail\Transport\SwgpiMailerTransport(
            (string) ($config['endpoint'] ?? ''), (string) ($config['key'] ?? '')
        ));

        RateLimiter::for('login', function (Request $request) {
            $identity = mb_strtolower((string) ($request->input('id') ?? $request->input('email') ?? 'unknown'));
            return Limit::perMinute(5)->by($request->ip().'|'.$identity);
        });

        RateLimiter::for('self-registration', function (Request $request) {
            $identity = mb_strtolower((string) ($request->input('email') ?? 'unknown'));
            return [Limit::perMinute(5)->by($request->ip().'|'.$identity), Limit::perHour(30)->by('self-registration|'.$request->ip())];
        });

                RateLimiter::for('password-recovery', function (Request $request) {
            $identity = mb_strtolower((string) ($request->input('email') ?? $request->input('correo') ?? 'unknown'));
            return Limit::perMinute(5)->by($request->path().'|'.$request->ip().'|'.$identity);
        });
    }
}
