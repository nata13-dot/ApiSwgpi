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

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $status = $exception instanceof ValidationException
                ? 422
                : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            $this->record($request, $status);
            throw $exception;
        }

        $this->record($request, $response->getStatusCode());

        return $response;
    }

    private function record(Request $request, int $status): void
    {
        try {
            DB::table('auditoria_carreras')->insert([
                'carrera_id' => app(CareerContext::class)->careerId(),
                'actor_id' => $request->user('api')?->id,
                'metodo' => $request->method(),
                'ruta' => '/'.$request->path(),
                'accion' => $request->route()?->getActionName(),
                'estado_http' => $status,
                'direccion_ip' => $request->ip(),
                'agente_usuario' => mb_substr((string) $request->userAgent(), 0, 255),
                'detalle' => json_encode([
                    'route_parameters' => collect($request->route()?->parameters() ?? [])
                        ->map(fn ($value) => is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value)
                        ->all(),
                    'query_keys' => array_keys($request->query()),
                ], JSON_UNESCAPED_UNICODE),
                'creado_en' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
