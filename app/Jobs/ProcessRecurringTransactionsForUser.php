<?php

namespace App\\Jobs;

use App\\Services\\RecurringTransactionProcessor;
use Illuminate\\Bus\\Queueable;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Foundation\\Bus\\Dispatchable;
use Illuminate\\Queue\\InteractsWithQueue;
use Illuminate\\Queue\\SerializesModels;
use Illuminate\\Support\\Facades\\Log;
use Throwable;

class ProcessRecurringTransactionsForUser implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $userId) {}

    public function handle(RecurringTransactionProcessor $processor): void
    {
        $startedAt = hrtime(true);
        $attempt = $this->attempts();

        Log::info('Recurring queue job started', [
            'event' => 'recurring.processing.job_started',
            'queue' => $this->queue ?? 'default',
            'attempt' => $attempt,
        ]);

        try {
            $processed = $processor->process($this->userId);
        } catch (Throwable $exception) {
            Log::warning('Recurring queue job attempt failed', [
                'event' => 'recurring.processing.job_attempt_failed',
                'queue' => $this->queue ?? 'default',
                'attempt' => $attempt,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);

            throw $exception;
        }

        Log::info('Recurring queue job completed', [
            'event' => 'recurring.processing.job_completed',
            'queue' => $this->queue ?? 'default',
            'attempt' => $attempt,
            'occurrences_processed' => $processed,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);

        Log::warning('Recurring user processing job failed permanently', [
            'event' => 'recurring.processing.job_failed',
            'queue' => $this->queue ?? 'default',
            'attempt' => $this->attempts(),
        ]);
    }
}
