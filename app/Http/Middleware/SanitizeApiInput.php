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

    public function handle(Request $request, Closure $next)
    {
        if ($request->isJson()) {
            $request->merge($this->sanitizeArray($request->all()));
        }

        $request->query->replace($this->sanitizeArray($request->query->all()));

        return $next($request);
    }

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
}