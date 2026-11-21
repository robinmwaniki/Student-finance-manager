<?php

namespace Tests\Feature;

use App\Jobs\QueueWorkerLifecycleProbe;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RedisQueueWorkerLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The Redis queue worker integration test requires the phpredis extension.');
        }

        config([
            'database.redis.client' => 'phpredis',
            'database.redis.default.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.default.port' => (int) env('REDIS_PORT', 6379),
            'database.redis.queue.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.queue.port' => (int) env('REDIS_PORT', 6379),
            'queue.default' => 'redis',
            'queue.connections.redis.connection' => 'queue',
            'queue.connections.redis.retry_after' => 30,
        ]);
    }

    public function test_separate_laravel_worker_process_executes_and_acknowledges_a_redis_job(): void
    {
        $queue = 'worker-lifecycle-'.Str::uuid();
        $directory = $this->makeProbeDirectory();
        $attemptsPath = $directory.'/attempts.txt';
        $successPath = $directory.'/success.txt';

        try {
            Queue::connection('redis')->push(
                new QueueWorkerLifecycleProbe($attemptsPath, $successPath),
                '',
                $queue,
            );

            $worker = $this->startWorker($queue);
            $worker->mustRun();

            $this->assertFileExists($successPath);
            $this->assertSame("executed\n", file_get_contents($successPath));
            $this->assertSame('1', trim((string) file_get_contents($attemptsPath)));
            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue));
            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue.':reserved'));
        } finally {
            $this->cleanUpProbe($queue, $directory);
        }
    }

    public function test_worker_retries_a_transient_job_failure_and_acknowledges_the_successful_attempt(): void
    {
        $queue = 'worker-retry-'.Str::uuid();
        $directory = $this->makeProbeDirectory();
        $attemptsPath = $directory.'/attempts.txt';
        $successPath = $directory.'/success.txt';

        try {
            Queue::connection('redis')->push(
                new QueueWorkerLifecycleProbe($attemptsPath, $successPath, failFirstAttempt: true),
                '',
                $queue,
            );

            $firstWorker = $this->startWorker($queue);
            $firstWorker->mustRun();

            $this->assertFileDoesNotExist($successPath);
            $this->assertSame('1', trim((string) file_get_contents($attemptsPath)));
            $pendingJobs = Redis::connection('queue')->llen('queues:'.$queue)
                + Redis::connection('queue')->zcard('queues:'.$queue.':delayed');
            $this->assertSame(1, $pendingJobs);

            $secondWorker = $this->startWorker($queue);
            $secondWorker->mustRun();

            $this->assertFileExists($successPath);
            $this->assertSame("executed\n", file_get_contents($successPath));
            $this->assertSame('2', trim((string) file_get_contents($attemptsPath)));
            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue));
            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue.':reserved'));
        } finally {
            $this->cleanUpProbe($queue, $directory);
        }
    }

    private function startWorker(string $queue): Process
    {
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'queue:work',
            'redis',
            '--queue='.$queue,
            '--once',
            '--tries=2',
            '--timeout=10',
            '--sleep=0',
            '--no-interaction',
        ], base_path(), $this->workerEnvironment());

        $process->setTimeout(20);

        return $process;
    }

    private function workerEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'QUEUE_CONNECTION' => 'redis',
            'REDIS_CLIENT' => 'phpredis',
            'REDIS_HOST' => (string) config('database.redis.queue.host'),
            'REDIS_PORT' => (string) config('database.redis.queue.port'),
            'REDIS_QUEUE_CONNECTION' => 'queue',
            'REDIS_QUEUE_RETRY_AFTER' => '30',
            'REDIS_QUEUE_DB' => (string) config('database.redis.queue.database'),
        ];
    }

    private function makeProbeDirectory(): string
    {
        $directory = sys_get_temp_dir().'/sfm-queue-worker-'.Str::uuid();

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->fail('Unable to create the isolated queue worker probe directory.');
        }

        return $directory;
    }

    private function cleanUpProbe(string $queue, string $directory): void
    {
        Redis::connection('queue')->del(
            'queues:'.$queue,
            'queues:'.$queue.':delayed',
            'queues:'.$queue.':reserved',
        );

        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
