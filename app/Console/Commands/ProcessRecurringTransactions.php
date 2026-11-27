<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRecurringTransactionsForUser;
use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'finance:process-recurring {--chunk=250 : Number of due users to scan per database batch (1-1000)} {--async : Dispatch each due user to the recurring queue instead of processing inline}';

    protected $description = 'Apply or queue due recurring income and expense schedules for all users';

    public function handle(RecurringTransactionProcessor $processor): int
    {
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        if ($chunkSize === false || $chunkSize < 1 || $chunkSize > 1000) {
            $this->components->error('The --chunk option must be an integer between 1 and 1000.');

            return self::INVALID;
        }

        $async = (bool) $this->option('async');
        $startedAt = hrtime(true);
        $processed = 0;
        $usersAttempted = 0;
        $usersQueued = 0;
        $failures = 0;
        $batchesProcessed = 0;

        Log::info('Recurring processing run started', [
            'event' => 'recurring.processing.started',
            'chunk_size' => $chunkSize,
            'mode' => $async ? 'queued' : 'inline',
        ]);

        RecurringTransaction::query()
            ->select('user_id')
            ->where('active', true)
            ->where('status', 'active')
            ->whereNotNull('next_due')
            ->whereDate('next_due', '<=', now(config('app.timezone'))->toDateString())
            ->distinct()
            ->chunkById($chunkSize, function ($users) use ($processor, $async, &$processed, &$usersAttempted, &$usersQueued, &$failures, &$batchesProcessed): void {
                $batchesProcessed++;

                foreach ($users as $user) {
                    $usersAttempted++;
                    $userId = (int) $user->user_id;

                    try {
                        if ($async) {
                            ProcessRecurringTransactionsForUser::dispatch($userId)->onQueue('recurring');
                            $usersQueued++;
                        } else {
                            $processed += $processor->process($userId);
                        }
                    } catch (Throwable $exception) {
                        $failures++;
                        report($exception);
                        $this->components->error('Recurring processing failed for one user. See application logs for the exception.');
                    }
                }
            }, 'user_id', 'user_id');

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $completionContext = [
            'event' => 'recurring.processing.completed',
            'chunk_size' => $chunkSize,
            'mode' => $async ? 'queued' : 'inline',
            'batches_processed' => $batchesProcessed,
            'users_attempted' => $usersAttempted,
            'users_queued' => $usersQueued,
            'occurrences_processed' => $processed,
            'failed_users' => $failures,
            'duration_ms' => $durationMs,
        ];

        if ($failures > 0) {
            Log::warning('Recurring processing run completed', $completionContext);
        } else {
            Log::info('Recurring processing run completed', $completionContext);
        }

        $summary = $async
            ? "Queued recurring processing for {$usersQueued} user(s)"
            : "Processed {$processed} recurring occurrence(s) across {$usersAttempted} user(s)";
        $this->components->info("{$summary} in {$durationMs}ms.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
