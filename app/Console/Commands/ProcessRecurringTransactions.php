<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionProcessor;
use Illuminate\Console\Command;
use Throwable;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'finance:process-recurring';

    protected $description = 'Apply due recurring income and expense schedules for all users';

    public function handle(RecurringTransactionProcessor $processor): int
    {
        $userIds = RecurringTransaction::query()
            ->where('active', true)
            ->where('status', 'active')
            ->whereNotNull('next_due')
            ->whereDate('next_due', '<=', now(config('app.timezone'))->toDateString())
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id');

        $processed = 0;
        $failures = 0;

        foreach ($userIds as $userId) {
            try {
                $processed += $processor->process((int) $userId);
            } catch (Throwable $exception) {
                $failures++;
                report($exception);
                $this->components->error("Recurring processing failed for user {$userId}: {$exception->getMessage()}");
            }
        }

        $this->components->info("Processed {$processed} recurring occurrence(s).");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
