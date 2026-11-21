<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedisMultiInstanceIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The Redis integration service requires the phpredis extension.');
        }

        config([
            'database.redis.client' => 'phpredis',
            'database.redis.default.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.default.port' => (int) env('REDIS_PORT', 6379),
            'database.redis.cache.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.cache.port' => (int) env('REDIS_PORT', 6379),
            'database.redis.session.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.session.port' => (int) env('REDIS_PORT', 6379),
            'database.redis.queue.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.queue.port' => (int) env('REDIS_PORT', 6379),
            'cache.stores.redis.connection' => 'cache',
            'session.driver' => 'redis',
            'session.connection' => 'session',
            'queue.connections.redis.connection' => 'queue',
        ]);
    }

    public function test_independent_application_cache_stores_share_values_locks_and_rate_limits(): void
    {
        $key = 'multi-instance:'.Str::uuid();
        $lockKey = $key.':lock';

        $firstInstance = Cache::store('redis');
        $firstInstance->put($key, 'written-by-instance-one', 30);

        $lock = $firstInstance->lock($lockKey, 30);
        $this->assertTrue($lock->get());

        $firstLimiter = new RateLimiter($firstInstance);
        $firstLimiter->hit($key.':rate-limit', 60);

        Cache::forgetDriver('redis');

        $secondInstance = Cache::store('redis');
        $this->assertSame('written-by-instance-one', $secondInstance->get($key));
        $this->assertFalse($secondInstance->lock($lockKey, 30)->get());

        $secondLimiter = new RateLimiter($secondInstance);
        $this->assertTrue($secondLimiter->tooManyAttempts($key.':rate-limit', 1));

        $lock->release();
        $secondLock = $secondInstance->lock($lockKey, 30);
        $this->assertTrue($secondLock->get());
        $secondLock->release();

        $secondInstance->forget($key);
        $secondInstance->forget($key.':rate-limit');
    }

    public function test_independent_session_managers_can_read_the_same_redis_session(): void
    {
        $sessionId = Str::random(40);

        $firstSession = app('session')->driver('redis');
        $firstSession->setId($sessionId);
        $firstSession->start();
        $firstSession->put('multi_instance_probe', 'session-from-instance-one');
        $firstSession->save();

        app('session')->forgetDrivers();

        $secondSession = app('session')->driver('redis');
        $secondSession->setId($sessionId);
        $secondSession->start();

        $this->assertSame('session-from-instance-one', $secondSession->get('multi_instance_probe'));

        $secondSession->flush();
        $secondSession->save();
        app('session')->forgetDrivers();
    }

    public function test_independent_redis_queue_connections_can_consume_each_others_jobs(): void
    {
        $queueName = 'multi-instance-'.Str::uuid();
        $payload = json_encode(['id' => (string) Str::uuid()], JSON_THROW_ON_ERROR);

        $firstQueue = Queue::connection('redis');
        $firstQueue->pushRaw($payload, $queueName);

        Queue::purge('redis');

        $secondQueue = Queue::connection('redis');
        $job = $secondQueue->pop($queueName);

        $this->assertNotNull($job);
        $this->assertSame($payload, $job->getRawBody());

        $job->delete();
        Queue::purge('redis');
    }
}
