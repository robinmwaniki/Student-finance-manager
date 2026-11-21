<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Jobs\QueueWorkerLifecycleProbe;
use Tests\TestCase;

class RedisQueueWorkerLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (extension_loaded('redis') === false) {
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

    public function test_worker_persists_a_job_after_all_attempts_fail(): void
    {
        $queue = 'worker-terminal-failure-'.Str::uuid();
        $directory = $this->makeProbeDirectory();
        $attemptsPath = $directory.'/attempts.txt';
        $successPath = $directory.'/success.txt';

        try {
            Queue::connection('redis')->push(
                new QueueWorkerLifecycleProbe($attemptsPath, $successPath, failEveryAttempt: true),
                '',
                $queue,
            );

            $firstWorker = $this->startWorker($queue);
            $firstWorker->mustRun();

            $this->assertSame('1', trim((string) file_get_contents($attemptsPath)));
            $pendingJobs = Redis::connection('queue')->llen('queues:'.$queue)
                + Redis::connection('queue')->zcard('queues:'.$queue.':delayed');
            $this->assertSame(1, $pendingJobs);

            $secondWorker = $this->startWorker($queue);
            $secondWorker->mustRun();

            $this->assertSame('2', trim((string) file_get_contents($attemptsPath)));
            $this->assertSame(1, DB::table('failed_jobs')->where('queue', $queue)->count());
            $this->assertFileDoesNotExist($successPath);
            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue));
            $this->assertSame(0, Redis::connection('queue')->zcard('queues:'.$queue.':delayed'));
            $this->assertSame(0, Redis::connection('queue')->zcard('queues:'.$queue.':reserved'));
        } finally {
            DB::table('failed_jobs')->where('queue', $queue)->delete();
            $this->cleanUpProbe($queue, $directory);
        }
    }

    public function test_worker_drains_a_queued_backlog_before_stopping(): void
    {
        $queue = 'worker-backlog-'.Str::uuid();
        $directory = $this->makeProbeDirectory();
        $jobCount = 8;
        $probes = [];

        try {
            for ($index = 0; $index < $jobCount; $index++) {
                $attemptsPath = $directory.'/attempts-'.$index.'.txt';
                $successPath = $directory.'/success-'.$index.'.txt';

                Queue::connection('redis')->push(
                    new QueueWorkerLifecycleProbe($attemptsPath, $successPath),
                    '',
                    $queue,
                );

                $probes[] = [$attemptsPath, $successPath];
            }

            $worker = $this->startWorker($queue, stopWhenEmpty: true);
            $worker->mustRun();

            foreach ($probes as [$attemptsPath, $successPath]) {
                $this->assertFileExists($successPath);
                $this->assertSame("executed\n", file_get_contents($successPath));
                $this->assertSame('1', trim((string) file_get_contents($attemptsPath)));
            }

            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue));
            $this->assertSame(0, Redis::connection('queue')->zcard('queues:'.$queue.':delayed'));
            $this->assertSame(0, Redis::connection('queue')->zcard('queues:'.$queue.':reserved'));
        } finally {
            $this->cleanUpProbe($queue, $directory);
        }
    }

    public function test_worker_restart_recovers_a_job_reserved_by_a_terminated_process(): void
    {
        $queue = 'worker-crash-recovery-'.Str::uuid();
        $directory = $this->makeProbeDirectory();
        $attemptsPath = $directory.'/attempts.txt';
        $successPath = $directory.'/success.txt';
        $startedPath = $directory.'/started.txt';
        $retryAfter = 4;
        $worker = null;

        try {
            config(['queue.connections.redis.retry_after' => $retryAfter]);

            Queue::connection('redis')->push(
                new QueueWorkerLifecycleProbe(
                    $attemptsPath,
                    $successPath,
                    startedPath: $startedPath,
                    holdFirstAttemptSeconds: 8,
                ),
                '',
                $queue,
            );

            $worker = $this->startWorker($queue, retryAfter: $retryAfter, timeout: 2);
            $worker->start();

            $deadline = microtime(true) + 10;

            while (file_exists($startedPath) === false && $worker->isRunning() && microtime(true) < $deadline) {
                usleep(100_000);
            }

            $this->assertFileExists($startedPath, 'The first worker did not reserve and start the probe job.');
            $this->assertTrue($worker->isRunning(), 'The probe worker exited before it could be terminated.');

            $worker->stop(0, 9);
            $this->assertFalse($worker->isRunning());

            sleep($retryAfter);
            sleep(1);

            $recoveryWorker = $this->startWorker($queue, retryAfter: $retryAfter, timeout: 2);
            $recoveryWorker->mustRun();

            $this->assertSame('2', trim((string) file_get_contents($attemptsPath)));
            $this->assertSame("executed\n", file_get_contents($successPath));
            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue));
            $this->assertSame(0, Redis::connection('queue')->llen('queues:'.$queue.':reserved'));
        } finally {
            if ($worker instanceof Process && $worker->isRunning()) {
                $worker->stop(0, 9);
            }

            $this->cleanUpProbe($queue, $directory);
        }
    }

    private function startWorker(string $queue, int $retryAfter = 30, int $timeout = 10, bool $stopWhenEmpty = false): Process
    {
        $arguments = [
            PHP_BINARY,
            'artisan',
            'queue:work',
            'redis',
            '--queue='.$queue,
        ];

        $arguments[] = $stopWhenEmpty ? '--stop-when-empty' : '--once';
        $arguments[] = '--tries=2';
        $arguments[] = '--timeout='.$timeout;
        $arguments[] = '--sleep=0';
        $arguments[] = '--no-interaction';

        $process = new Process($arguments, base_path(), $this->workerEnvironment($retryAfter));

        $process->setTimeout(20);

        return $process;
    }

    private function workerEnvironment(int $retryAfter): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            'QUEUE_CONNECTION' => 'redis',
            'REDIS_CLIENT' => 'phpredis',
            'REDIS_HOST' => (string) config('database.redis.queue.host'),
            'REDIS_PORT' => (string) config('database.redis.queue.port'),
            'REDIS_QUEUE_CONNECTION' => 'queue',
            'REDIS_QUEUE_RETRY_AFTER' => (string) $retryAfter,
            'REDIS_QUEUE_DB' => (string) config('database.redis.queue.database'),
        ];
    }

    private function makeProbeDirectory(): string
    {
        $directory = sys_get_temp_dir().'/sfm-queue-worker-'.Str::uuid();

        if (mkdir($directory, 0700, true) === false && is_dir($directory) === false) {
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
