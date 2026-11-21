<?php

namespace Tests\Feature;

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
}
