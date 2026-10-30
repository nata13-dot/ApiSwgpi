<?php

namespace App\Support;

use Closure;

/**
 * Memoria que vive lo que dura la petición actual. A diferencia de una propiedad estática,
 * no se comparte entre peticiones atendidas por el mismo proceso (pruebas, Octane).
 */
final class RequestMemo
{
    public static function remember(string $key, Closure $resolve): mixed
    {
        $attributes = request()->attributes;
        $key = "memo:{$key}";
        if (!$attributes->has($key)) {
            $attributes->set($key, $resolve());
        }

        return $attributes->get($key);
    }

    public static function forget(string $prefix): void
    {
        $attributes = request()->attributes;
        foreach (array_keys($attributes->all()) as $key) {
            if (str_starts_with($key, "memo:{$prefix}")) {
                $attributes->remove($key);
            }
        }
    }
}
