<?php

namespace Tests\Feature;

use Tests\TestCase;

class MultiInstanceReadinessCommandTest extends TestCase
{
    public function test_readiness_check_flags_local_only_session_and_cache_stores(): void
    {
        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'database',
            'queue.connections.database.driver' => 'database',
        ]);

        $this->artisan('finance:deployment-readiness')
            ->expectsOutputToContain('BLOCKED Sessions: file')
            ->expectsOutputToContain('BLOCKED Cache and distributed locks: file')
            ->expectsOutputToContain('READY Background queue: database')
            ->expectsOutputToContain('Configuration-only result: 1/3 checks ready.')
            ->assertExitCode(0);
    }

    public function test_strict_readiness_check_fails_when_local_only_stores_are_configured(): void
    {
        config([
            'session.driver' => 'file',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'queue.connections.sync.driver' => 'sync',
        ]);

        $this->artisan('finance:deployment-readiness --strict')
            ->assertExitCode(1);
    }

    public function test_readiness_check_accepts_shared_session_cache_and_queue_drivers(): void
    {
        config([
            'session.driver' => 'redis',
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'queue.connections.redis.driver' => 'redis',
        ]);

        $this->artisan('finance:deployment-readiness --strict')
            ->expectsOutputToContain('READY Sessions: redis')
            ->expectsOutputToContain('READY Cache and distributed locks: redis')
            ->expectsOutputToContain('READY Background queue: redis')
            ->expectsOutputToContain('Configuration-only result: 3/3 checks ready.')
            ->assertExitCode(0);
    }
}
