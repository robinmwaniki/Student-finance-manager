<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
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

    public function test_separate_php_processes_share_redis_backed_session_state(): void
    {
        $sessionId = Str::random(40);
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'session.driver' => 'redis',
    'session.connection' => 'session',
]);
$store = app('session')->driver('redis');
$store->setId($argv[2]);
$store->start();
if ($argv[3] === 'write') {
    $store->put('cross_process_probe', 'written-by-first-php-process');
}
echo json_encode(['value' => $store->get('cross_process_probe')], JSON_THROW_ON_ERROR);
$store->save();
PHP;

        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'REDIS_CLIENT' => 'phpredis',
            'REDIS_HOST' => (string) config('database.redis.session.host'),
            'REDIS_PORT' => (string) config('database.redis.session.port'),
            'REDIS_SESSION_DB' => (string) config('database.redis.session.database'),
        ];

        $writer = new Process([PHP_BINARY, '-r', $script, base_path(), $sessionId, 'write'], base_path(), $environment);
        $writer->mustRun();

        $reader = new Process([PHP_BINARY, '-r', $script, base_path(), $sessionId, 'read'], base_path(), $environment);
        $reader->mustRun();

        $result = json_decode($reader->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('written-by-first-php-process', $result['value'] ?? null);
    }

    public function test_separate_php_processes_share_redis_locks_rate_limits_and_queue_jobs(): void
    {
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'cache.default' => 'redis',
    'cache.stores.redis.connection' => 'cache',
    'queue.default' => 'redis',
    'queue.connections.redis.connection' => 'queue',
]);
$mode = $argv[2];
$key = $argv[3];
if ($mode === 'lock-acquire' || $mode === 'lock-check') {
    $lock = app('cache')->store('redis')->lock($key, 60);
    echo json_encode(['acquired' => $lock->get()], JSON_THROW_ON_ERROR);
} elseif ($mode === 'rate-hit' || $mode === 'rate-check') {
    $limiter = new Illuminate\Cache\RateLimiter(app('cache')->store('redis'));
    if ($mode === 'rate-hit') {
        $limiter->hit($key, 60);
        echo json_encode(['hit' => true], JSON_THROW_ON_ERROR);
    } else {
        echo json_encode(['limited' => $limiter->tooManyAttempts($key, 1)], JSON_THROW_ON_ERROR);
    }
} elseif ($mode === 'queue-push') {
    app('queue')->connection('redis')->pushRaw($argv[4], $key);
    echo json_encode(['pushed' => true], JSON_THROW_ON_ERROR);
} elseif ($mode === 'queue-pop') {
    $job = app('queue')->connection('redis')->pop($key);
    $body = $job?->getRawBody();
    $job?->delete();
    echo json_encode(['body' => $body], JSON_THROW_ON_ERROR);
}
PHP;

        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'REDIS_CLIENT' => 'phpredis',
            'REDIS_HOST' => (string) config('database.redis.default.host'),
            'REDIS_PORT' => (string) config('database.redis.default.port'),
            'REDIS_CACHE_DB' => (string) config('database.redis.cache.database'),
            'REDIS_QUEUE_DB' => (string) config('database.redis.queue.database'),
        ];

        $run = function (string $mode, string $key, ?string $payload = null) use ($script, $environment): array {
            $arguments = [PHP_BINARY, '-r', $script, base_path(), $mode, $key];
            if ($payload !== null) {
                $arguments[] = $payload;
            }

            $process = new Process($arguments, base_path(), $environment);
            $process->mustRun();

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        };

        $lockKey = 'cross-process-lock:'.Str::uuid();
        $this->assertTrue($run('lock-acquire', $lockKey)['acquired']);
        $this->assertFalse($run('lock-check', $lockKey)['acquired']);

        $rateKey = 'cross-process-rate-limit:'.Str::uuid();
        $run('rate-hit', $rateKey);
        $this->assertTrue($run('rate-check', $rateKey)['limited']);

        $queueName = 'cross-process-queue:'.Str::uuid();
        $payload = json_encode(['id' => (string) Str::uuid()], JSON_THROW_ON_ERROR);
        $run('queue-push', $queueName, $payload);
        $this->assertSame($payload, $run('queue-pop', $queueName)['body']);
    }

    public function test_independent_redis_connections_share_queue_transport_keys(): void
    {
        $queueName = 'multi-instance-'.Str::uuid();
        $queueKey = 'queues:'.$queueName;
        $payload = json_encode(['id' => (string) Str::uuid()], JSON_THROW_ON_ERROR);

        Redis::connection('queue')->rpush($queueKey, $payload);

        app('redis')->purge('queue');

        $messageFromSecondConnection = Redis::connection('queue')->lpop($queueKey);

        $this->assertSame($payload, $messageFromSecondConnection);
    }
}
