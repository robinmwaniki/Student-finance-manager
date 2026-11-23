<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ScaleInfrastructureConfigTest extends TestCase
{
    public function test_redis_backends_are_available_without_requiring_redis_in_local_tests(): void
    {
        $this->assertSame('redis', config('cache.stores.redis.driver'));
        $this->assertSame('cache', config('cache.stores.redis.connection'));
        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertSame('queue', config('queue.connections.redis.connection'));
        $this->assertSame('session', config('session.connection'));
        $this->assertSame('1', (string) config('database.redis.cache.database'));
        $this->assertSame('2', (string) config('database.redis.session.database'));
        $this->assertSame('3', (string) config('database.redis.queue.database'));
        $this->assertNull(config('database.redis.cache.url'));
        $this->assertNull(config('database.redis.session.url'));
        $this->assertNull(config('database.redis.queue.url'));
    }

    public function test_slow_database_query_logs_metadata_without_sql_or_binding_values(): void
    {
        config(['performance.slow_database_query_threshold_ms' => 0]);
        Log::spy();

        DB::select('select ? as synthetic_value', ['sensitive-fixture']);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Slow database query'
                    && isset($context['connection'])
                    && isset($context['duration_ms'])
                    && $context['bindings_count'] === 1
                    && isset($context['route_name'])
                    && ! array_key_exists('sql', $context)
                    && ! array_key_exists('bindings', $context);
            })
            ->atLeast()
            ->once();
    }
}
