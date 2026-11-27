<?php

namespace App\\Jobs;

use App\\Services\\RecurringTransactionProcessor;
use Illuminate\\Bus\\Queueable;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Foundation\\Bus\\Dispatchable;
use Illuminate\\Queue\\InteractsWithQueue;
use Illuminate\\Queue\\SerializesModels;
use Throwable;

class ProcessRecurringTransactionsForUser implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $userId)
    {
    }

    public function handle(RecurringTransactionProcessor $processor): void
    {
        $processor->process($this->userId);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);

        \\Illuminate\\Support\\Facades\\Log::warning('Recurring user processing job failed permanently', [
            'event' => 'recurring.processing.job_failed',
        ]);
    }
}
