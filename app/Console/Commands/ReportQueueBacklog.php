<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Log;
use Illuminate\\Support\\Facades\\Queue;

class ReportQueueBacklog extends Command
{
    protected $signature = 'finance:queue-metrics {--queue=recurring : Queue name to inspect}';

    protected $description = 'Report pending queue depth and oldest pending job age';

    public function handle(): int
    {
        $queue = trim((string) $this->option('queue'));
        if ($queue === '') {
            $this->components->error('The --queue option must not be empty.');

            return self::INVALID;
        }

        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);
        $pendingJobs = Queue::connection($connection)->size($queue);
        $oldestJobAgeSeconds = null;

        if ($driver === 'database') {
            $table = (string) config("queue.connections.{$connection}.table", 'jobs');
            $databaseConnection = config("queue.connections.{$connection}.connection")
                ?: config('database.default');
            $waitingJobs = DB::connection($databaseConnection)
                ->table($table)
                ->where('queue', $queue)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->timestamp);

            $pendingJobs = (clone $waitingJobs)->count();
            $oldestCreatedAt = (clone $waitingJobs)->min('created_at');

            if ($oldestCreatedAt !== null) {
                $oldestJobAgeSeconds = max(0, now()->timestamp - (int) $oldestCreatedAt);
            }
        }

        $metrics = [
            'event' => 'queue.backlog.snapshot',
            'connection' => $connection,
            'driver' => $driver,
            'queue' => $queue,
            'pending_jobs' => $pendingJobs,
            'oldest_job_age_seconds' => $oldestJobAgeSeconds,
        ];

        Log::info('Queue backlog snapshot', $metrics);
        $this->line(json_encode($metrics, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
