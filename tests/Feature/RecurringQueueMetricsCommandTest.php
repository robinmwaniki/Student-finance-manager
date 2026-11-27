<?php

namespace Tests\Feature;

use App\Jobs\ProcessRecurringTransactionsForUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecurringQueueMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_queue_metrics_reports_pending_jobs_and_oldest_job_age(): void
    {
        Carbon::setTestNow('2026-11-27 12:00:00');
        config(['queue.default' => 'database']);

        Queue::connection('database')->push(
            new ProcessRecurringTransactionsForUser(123),
            '',
            'recurring',
        );

        DB::table('jobs')
            ->where('queue', 'recurring')
            ->update(['created_at' => now()->timestamp - 120]);

        $this->artisan('finance:queue-metrics', ['--queue' => 'recurring'])
            ->expectsOutputToContain('"queue":"recurring"')
            ->expectsOutputToContain('"pending_jobs":1')
            ->expectsOutputToContain('"oldest_job_age_seconds":120')
            ->assertExitCode(0);
    }

    public function test_queue_metrics_reports_zero_pending_jobs_for_an_empty_queue(): void
    {
        config(['queue.default' => 'database']);

        $this->artisan('finance:queue-metrics', ['--queue' => 'recurring'])
            ->expectsOutputToContain('"pending_jobs":0')
            ->expectsOutputToContain('"oldest_job_age_seconds":null')
            ->assertExitCode(0);
    }
}
