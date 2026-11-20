<?php

namespace App\Http\Middleware;

use App\Support\CareerContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Validation\ValidationException;

class AuditCareerMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || in_array($request->path(), ['api/auth/heartbeat', 'api/auth/logout'], true)) {
            return $next($request);
        }

        // El objetivo (nombre, número de control, título) se lee antes: tras eliminar ya no existiría.
        try { $before = \App\Support\AuditDescriber::before($request); } catch (\Throwable) { $before = ['accion' => null, 'frase' => null, 'tipo' => null, 'id' => null, 'objetivo' => null]; }

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $status = $exception instanceof ValidationException
                ? 422
                : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            $error = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->take(3)->implode(' ')
                : ($exception instanceof HttpExceptionInterface ? $exception->getMessage() : 'Error interno del servidor');
            $this->record($request, $status, $before, null, $error ?: null);
            throw $exception;
        }

        $body = null;
        if (str_contains((string) $response->headers->get('Content-Type'), 'json') && strlen((string) $response->getContent()) < 200000) {
            $body = json_decode((string) $response->getContent(), true);
        }
        $this->record($request, $response->getStatusCode(), $before, is_array($body) ? $body : null);

        return $response;
    }

    private function record(Request $request, int $status, array $before, ?array $response, ?string $error = null): void
    {
        try {
            try { $described = \App\Support\AuditDescriber::describe($request, $before, $status, $response, $error); }
            catch (\Throwable) { $described = ['descripcion' => null, 'objetivo' => null, 'detalles' => [], 'nivel' => null, 'afectados' => []]; }
            DB::table('auditoria_carreras')->insert([
                'carrera_id' => app(CareerContext::class)->careerId(),
                'actor_id' => $request->user('api')?->id,
                'metodo' => $request->method(),
                'ruta' => '/'.$request->path(),
                'accion' => $request->route()?->getActionName(),
                'estado_http' => $status,
                'direccion_ip' => \App\Support\ClientIp::of($request),
                'agente_usuario' => mb_substr((string) $request->userAgent(), 0, 255),
                'detalle' => json_encode([
                    'route_parameters' => collect($request->route()?->parameters() ?? [])
                        ->map(fn ($value) => is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value)
                        ->all(),
                    'query_keys' => array_keys($request->query()),
                    // Versión de la app que hizo el cambio (la web no la envía).
                    'cliente' => mb_substr((string) $request->headers->get('X-SGPI-Client'), 0, 40) ?: null,
                    // Qué se hizo, a quién o a qué, y los detalles (sin contraseñas ni códigos).
                    'descripcion' => $described['descripcion'],
                    'objetivo' => $described['objetivo'],
                    'detalles' => $described['detalles'],
                    'nivel' => $described['nivel'],
                    // Personas afectadas (ids de usuario): permiten ver «lo que le hicieron» a cada quien.
                    'afectados' => $described['afectados'],
                ], JSON_UNESCAPED_UNICODE),
                'creado_en' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
