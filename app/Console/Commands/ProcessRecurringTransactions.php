<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRecurringTransactionsForUser;
use App\Models\RecurringTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'finance:process-recurring {--chunk=250 : Number of due users to scan per database batch (1-1000)}';

    protected $description = 'Queue due recurring income and expense schedules for processing';

    public function handle(): int
    {
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        if ($chunkSize === false || $chunkSize < 1 || $chunkSize > 1000) {
            $this->components->error('The --chunk option must be an integer between 1 and 1000.');

            return self::INVALID;
        }

        $startedAt = hrtime(true);
        $usersQueued = 0;
        $failures = 0;
        $batchesProcessed = 0;

        Log::info('Recurring processing dispatch started', [
            'event' => 'recurring.processing.dispatch_started',
            'chunk_size' => $chunkSize,
        ]);

        RecurringTransaction::query()
            ->select('user_id')
            ->where('active', true)
            ->where('status', 'active')
            ->whereNotNull('next_due')
            ->whereDate('next_due', '<=', now(config('app.timezone'))->toDateString())
            ->distinct()
            ->chunkById($chunkSize, function ($users) use (&$usersQueued, &$failures, &$batchesProcessed): void {
                $batchesProcessed++;

                foreach ($users as $user) {
                    try {
                        ProcessRecurringTransactionsForUser::dispatch((int) $user->user_id)
                            ->onQueue('recurring');
                        $usersQueued++;
                    } catch (Throwable $exception) {
                        $failures++;
                        report($exception);
                    }
                }
            }, 'user_id', 'user_id');

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $context = [
            'event' => 'recurring.processing.dispatch_completed',
            'chunk_size' => $chunkSize,
            'batches_processed' => $batchesProcessed,
            'users_queued' => $usersQueued,
            'dispatch_failures' => $failures,
            'duration_ms' => $durationMs,
        ];

        if ($failures > 0) {
            Log::warning('Recurring processing dispatch completed with failures', $context);
        } else {
            Log::info('Recurring processing dispatch completed', $context);
        }

        $this->components->info("Queued recurring processing for {$usersQueued} user(s) in {$durationMs}ms.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
