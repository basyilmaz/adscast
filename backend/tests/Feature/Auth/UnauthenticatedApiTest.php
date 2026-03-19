<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnauthenticatedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_returns_401_json_instead_of_500(): void
    {
        $response = $this->get('/api/v1/workspaces');

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthenticated');
    }
}
