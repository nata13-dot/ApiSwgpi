<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\EnsureCorsHeaders::class);
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->redirectGuestsTo(null);

        $middleware->api(prepend: [
            \App\Http\Middleware\SanitizeApiInput::class,
        ]);
        
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'career' => \App\Http\Middleware\ResolveCareerContext::class,
            'career.module' => \App\Http\Middleware\EnsureCareerModuleEnabled::class,
            'audit' => \App\Http\Middleware\AuditCareerMutation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $exception,
            \Illuminate\Http\Request $request
        ) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json(['error' => 'No autenticado.'], 401);
        });

        $exceptions->render(function (
            \Tymon\JWTAuth\Exceptions\JWTException $exception,
            \Illuminate\Http\Request $request
        ) {
            if (!$request->is('api/*')) {
                return null;
            }

            $message = $exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException
                ? 'La sesion ha expirado.'
                : 'No autenticado.';

            return response()->json(['error' => $message], 401);
        });

        $exceptions->respond(function (
            \Symfony\Component\HttpFoundation\Response $response,
            \Throwable $exception,
            \Illuminate\Http\Request $request
        ) {
            return \App\Http\Middleware\EnsureCorsHeaders::apply($response, $request);
        });
    })->create();
