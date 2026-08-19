<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Exceptions\JWTException;

class CorsHeadersTest extends TestCase
{
    public function test_production_frontend_can_complete_api_preflight(): void
    {
        $response = $this
            ->withHeaders([
                'Origin' => 'https://swgpi.online',
                'Access-Control-Request-Method' => 'GET',
                'Access-Control-Request-Headers' => 'authorization,content-type',
            ])
            ->options('/api/dashboard/stats');

        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://swgpi.online')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_capacitor_app_can_complete_api_preflight(): void
    {
        foreach (['capacitor://localhost', 'https://localhost'] as $origin) {
            $response = $this
                ->withHeaders([
                    'Origin' => $origin,
                    'Access-Control-Request-Method' => 'POST',
                    'Access-Control-Request-Headers' => 'content-type,accept',
                ])
                ->options('/api/auth/login');

            $response
                ->assertNoContent()
                ->assertHeader('Access-Control-Allow-Origin', $origin)
                ->assertHeader('Access-Control-Allow-Credentials', 'true');
        }
    }

    public function test_unauthorized_api_response_keeps_cors_headers(): void
    {
        $response = $this
            ->withHeader('Origin', 'https://swgpi.online')
            ->getJson('/api/dashboard/stats');

        $response
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', 'https://swgpi.online')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_jwt_exception_becomes_cors_enabled_unauthorized_response(): void
    {
        Route::get('/api/test-jwt-exception', function () {
            throw new JWTException('Token could not be parsed.');
        });

        $response = $this
            ->withHeader('Origin', 'https://swgpi.online')
            ->getJson('/api/test-jwt-exception');

        $response
            ->assertUnauthorized()
            ->assertJson(['error' => 'No autenticado.'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://swgpi.online');
    }

    public function test_internal_api_error_keeps_cors_headers(): void
    {
        Route::get('/api/test-internal-error', function () {
            throw new \RuntimeException('Expected test exception.');
        });

        $response = $this
            ->withHeader('Origin', 'https://swgpi.online')
            ->getJson('/api/test-internal-error');

        $response
            ->assertInternalServerError()
            ->assertHeader('Access-Control-Allow-Origin', 'https://swgpi.online');
    }

    public function test_untrusted_origin_never_receives_credentialed_cors_headers(): void
    {
        Route::get('/api/test-untrusted-cors', fn () => response()->json(['ok' => true]));

        $this->withHeader('Origin', 'https://attacker.example')
            ->getJson('/api/test-untrusted-cors')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_preflight_does_not_reflect_unapproved_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://swgpi.online',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization, x-injected-header',
        ])->options('/api/auth/login');

        $response->assertNoContent();
        $this->assertStringContainsString('authorization', strtolower((string) $response->headers->get('Access-Control-Allow-Headers')));
        $this->assertStringNotContainsString('x-injected-header', strtolower((string) $response->headers->get('Access-Control-Allow-Headers')));
    }
}
