<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_successful_response(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_home_page_displays_the_public_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Student Finance Manager')
            ->assertSee('Make your money')
            ->assertSee('Track income')
            ->assertSee('Explore features');
    }
}
