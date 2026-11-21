<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class RequestCorrelationIdTest extends TestCase
{
    public function test_valid_request_id_is_returned_in_response_header(): void
    {
        $requestId = (string) Str::uuid();

        $this->withHeader('X-Request-ID', $requestId)
            ->get('/up')
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId);
    }

    public function test_response_includes_server_request_duration_header(): void
    {
        $response = $this->get('/up')->assertOk();

        $this->assertMatchesRegularExpression('/^\\d+\\.\\d{2}$/', $response->headers->get('X-Response-Time-Ms'));
    }

    public function test_invalid_request_id_is_replaced_with_a_generated_uuid(): void
    {
        $response = $this->withHeader('X-Request-ID', 'attacker-controlled-value')
            ->get('/up')
            ->assertOk();

        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
        $this->assertNotSame('attacker-controlled-value', $response->headers->get('X-Request-ID'));
    }
}
