<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
