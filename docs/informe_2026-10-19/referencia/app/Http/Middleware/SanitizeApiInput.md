# SanitizeApiInput

Fuente: [app/Http/Middleware/SanitizeApiInput.php](../../../../../../app/Http/Middleware/SanitizeApiInput.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeApiInput
{
    private array $except = [
        'password',
        'password_confirmation',
        'admin_password',
        'archivo',
    ];


}
```

## handle

Visibilidad: `public`. Líneas originales 17–26.

```php
    public function handle(Request $request, Closure $next)
    {
        if ($request->isJson()) {
            $request->merge($this->sanitizeArray($request->all()));
        }

        $request->query->replace($this->sanitizeArray($request->query->all()));

        return $next($request);
    }
```

## sanitizeArray

Visibilidad: `private`. Líneas originales 28–48.

```php
    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->except, true)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
                continue;
            }

            if (is_string($value)) {
                $value = str_replace("\0", '', $value);
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
                $data[$key] = trim(strip_tags($value));
            }
        }

        return $data;
    }
```
