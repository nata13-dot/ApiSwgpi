<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    public function test_protected_api_routes_return_json_unauthorized_response(): void
    {
        foreach ([
            '/api/activity-notifications',
            '/api/evaluations/projects',
        ] as $route) {
            $this->get($route)
                ->assertUnauthorized()
                ->assertJson(['error' => 'No autenticado.']);
        }
    }
}
