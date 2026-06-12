<?php

namespace Tests\Feature;

use Tests\TestCase;

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
}
