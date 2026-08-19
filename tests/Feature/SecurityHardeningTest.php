<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_api_responses_include_defensive_headers(): void
    {
        Route::get('/api/test-security-headers', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/test-security-headers')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; sandbox");
    }

    public function test_authentication_responses_are_not_cacheable(): void
    {
        $this->getJson('/api/auth/careers')
            ->assertUnauthorized()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_login_rate_limiter_blocks_the_sixth_attempt(): void
    {
        Route::post('/api/test-login-throttle', fn () => response()->json(['ok' => true]))
            ->middleware('throttle:login');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/test-login-throttle', ['id' => 'rate-test'])
                ->assertOk();
        }

        $this->postJson('/api/test-login-throttle', ['id' => 'rate-test'])
            ->assertStatus(429);
    }

    public function test_general_administration_routes_keep_required_guards(): void
    {
        foreach ([
            'api/admin/operational-alerts',
            'api/admin/database-backups',
            'api/admin/continuity-policy',
            'api/admin/careers',
        ] as $uri) {
            $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === $uri);
            $this->assertNotNull($route, "No se encontró la ruta {$uri}.");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:api', $middleware);
            $this->assertContains('role:general_admin', $middleware);
            $this->assertContains('audit', $middleware);
        }
    }

    public function test_private_backups_are_outside_the_public_directory(): void
    {
        $backupDirectory = storage_path('app/private/database-backups');
        $publicDirectory = realpath(public_path());

        $this->assertFalse(str_starts_with($backupDirectory, $publicDirectory.DIRECTORY_SEPARATOR));
        $this->assertFileDoesNotExist(public_path('.env'));
        $this->assertFileDoesNotExist(public_path('storage/app/private/database-backups'));
    }

    public function test_user_mutations_require_general_administrator_governance(): void
    {
        foreach ([
            ['POST', 'api/users'],
            ['PUT', 'api/users/{id}'],
            ['DELETE', 'api/users/{id}'],
            ['POST', 'api/users/{id}/toggle-active'],
            ['POST', 'api/users/import-excel'],
            ['POST', 'api/users/send-credentials'],
            ['GET', 'api/users/{id}'],
        ] as [$method, $uri]) {
            $route = collect(Route::getRoutes())->first(fn ($route) =>
                $route->uri() === $uri && in_array($method, $route->methods(), true)
            );
            $this->assertNotNull($route, "No se encontró la ruta {$method} {$uri}.");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:api', $middleware);
            $this->assertContains('role:user_governance', $middleware);
            $this->assertContains('audit', $middleware);
        }
    }
}
