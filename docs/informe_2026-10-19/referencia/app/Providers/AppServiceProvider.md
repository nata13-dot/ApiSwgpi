# AppServiceProvider

Fuente: [app/Providers/AppServiceProvider.php](../../../../../app/Providers/AppServiceProvider.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
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

    /**
     * Bootstrap any application services.
     */
}
```

## register

Visibilidad: `public`. Líneas originales 16–19.

```php
    public function register(): void
    {
        $this->app->scoped(CareerContext::class, fn () => new CareerContext());
    }
```

## boot

Visibilidad: `public`. Líneas originales 24–35.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `correo`, `email`, `id`.

```php
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
```
