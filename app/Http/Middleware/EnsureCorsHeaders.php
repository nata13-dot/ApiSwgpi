<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return self::apply(response('', 204), $request);
        }

        return self::apply($next($request), $request);
    }

    public static function apply(Response $response, Request $request): Response
    {
        $origin = $request->headers->get('Origin');

        if (!$origin || !self::isAllowedOrigin($origin)) {
            return $response;
        }

        $requestedHeaders = $request->headers->get(
            'Access-Control-Request-Headers',
            'Authorization, Content-Type, Accept, Origin, X-Requested-With, X-SGPI-Remember'
        );

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', $requestedHeaders);
        $response->headers->set('Access-Control-Max-Age', '600');
        $response->headers->set('Vary', 'Origin', false);

        return $response;
    }

    private static function isAllowedOrigin(string $origin): bool
    {
        $allowedOrigins = array_merge(
            [
                'https://swgpi.online',
                'https://www.swgpi.online',
                'https://frontsgwpi-production.up.railway.app',
            ],
            config('cors.allowed_origins', [])
        );

        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if (@preg_match($pattern, $origin) === 1) {
                return true;
            }
        }

        return false;
    }
}
