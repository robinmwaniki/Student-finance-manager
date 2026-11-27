<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckMultiInstanceReadiness extends Command
{
    protected $signature = 'finance:deployment-readiness {--strict : Exit with failure when a local-only store is configured}';

    protected $description = 'Check whether session, cache and queue drivers are suitable for multiple application instances';

    public function handle(): int
    {
        $sessionDriver = (string) config('session.driver');
        $cacheStore = (string) config('cache.default');
        $queueConnection = (string) config('queue.default');
        $queueDriver = (string) config("queue.connections.{$queueConnection}.driver", $queueConnection);

        $checks = [
            [
                'name' => 'Sessions',
                'driver' => $sessionDriver,
                'ready' => ! in_array($sessionDriver, ['file', 'array'], true),
                'reason' => 'File and array sessions are local to one process or container.',
            ],
            [
                'name' => 'Cache and distributed locks',
                'driver' => $cacheStore,
                'ready' => ! in_array($cacheStore, ['file', 'array'], true),
                'reason' => 'File and array cache stores are not shared across application instances.',
            ],
            [
                'name' => 'Background queue',
                'driver' => $queueDriver,
                'ready' => ! in_array($queueDriver, ['sync', 'null'], true),
                'reason' => 'Sync and null queues do not provide a shared background work queue.',
            ],
        ];

        $blocked = 0;

        foreach ($checks as $check) {
            if ($check['ready']) {
                $this->line(sprintf(
                    '<info>READY</info> %s: %s',
                    $check['name'],
                    $check['driver'],
                ));

                continue;
            }

            $blocked++;
            $this->line(sprintf(
                '<error>BLOCKED</error> %s: %s — %s',
                $check['name'],
                $check['driver'],
                $check['reason'],
            ));
        }

        $this->newLine();
        $this->line(sprintf(
            'Configuration-only result: %d/%d checks ready.',
            count($checks) - $blocked,
            count($checks),
        ));
        $this->comment('This check does not test store connectivity, database pool limits, worker availability, or failover.');

        if ($blocked > 0 && $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
