<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringTransactionProcessor
{
    /**
     * Apply every currently due occurrence for one schedule. Each occurrence is
     * committed with its ledger row and next_due update as a single transaction.
     */
    public function process(int $userId, ?int $recurringId = null): int
    {
        $query = RecurringTransaction::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->where('status', 'active')
            ->whereNotNull('next_due')
            ->whereDate('next_due', '<=', now(config('app.timezone'))->toDateString())
            ->orderBy('next_due')
            ->orderBy('id');

        if ($recurringId !== null) {
            $query->whereKey($recurringId);
        }

        $processed = 0;
        foreach ($query->pluck('id') as $id) {
            // Bound one request to a year of catch-up work per schedule. Further
            // overdue occurrences remain due and can be processed on the next run.
            for ($attempt = 0; $attempt < 366; $attempt++) {
                $applied = $this->processNextOccurrence($userId, (int) $id);
                if (!$applied) {
                    break;
                }
                $processed++;
            }
        }

        return $processed;
    }

    private function processNextOccurrence(int $userId, int $id): bool
    {
        return DB::transaction(function () use ($userId, $id): bool {
            $schedule = RecurringTransaction::query()
                ->where('user_id', $userId)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $schedule || ! $schedule->active || $schedule->status !== 'active' || ! $schedule->next_due) {
                return false;
            }

            $due = CarbonImmutable::parse($schedule->next_due->toDateString());
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
            if ($due->greaterThan($today)) {
                return false;
            }

            $periodKey = match ($schedule->frequency) {
                'daily' => 'D:'.$due->toDateString(),
                'weekly' => 'W:'.$due->isoWeekYear().'-'.str_pad((string) $due->isoWeek(), 2, '0', STR_PAD_LEFT),
                'monthly' => 'M:'.$due->format('Y-m'),
                default => throw ValidationException::withMessages(['frequency' => 'This recurring frequency is not supported.']),
            };

            $alreadyApplied = $schedule->logs()->where('period_key', $periodKey)->exists();
            if (!$alreadyApplied) {
                $ledger = [
                    'user_id' => $userId,
                    'amount' => $schedule->amount,
                    'transaction_date' => $due->toDateString(),
                    'tag' => 'Recurring',
                ];

                if ($schedule->type === 'income') {
                    $income = $schedule->user->income()->create([
                        ...$ledger,
                        'source' => $schedule->label ?: ($schedule->category ?: 'Recurring income'),
                    ]);
                    $schedule->logs()->create([
                        'user_id' => $userId,
                        'applied_month' => $due->format('Y-m'),
                        'period_key' => $periodKey,
                        'income_id' => $income->id,
                        'applied_at' => now(),
                    ]);
                } elseif ($schedule->type === 'expense') {
                    $expense = $schedule->user->expenses()->create([
                        ...$ledger,
                        'category' => $schedule->category ?: 'Other',
                    ]);
                    $schedule->logs()->create([
                        'user_id' => $userId,
                        'applied_month' => $due->format('Y-m'),
                        'period_key' => $periodKey,
                        'expense_id' => $expense->id,
                        'applied_at' => now(),
                    ]);
                } else {
                    throw ValidationException::withMessages(['type' => 'This recurring transaction type is not supported.']);
                }
            }

            $schedule->next_due = $this->nextDue($due, $schedule->frequency, (int) $schedule->day_of_month);
            $schedule->save();

            return true;
        }, 3);
    }

    private function nextDue(CarbonImmutable $due, string $frequency, int $dayOfMonth): CarbonImmutable
    {
        return match ($frequency) {
            'daily' => $due->addDay(),
            'weekly' => $due->addWeek(),
            'monthly' => $due->startOfMonth()->addMonth()->day(min($dayOfMonth, $due->startOfMonth()->addMonth()->daysInMonth)),
            default => throw ValidationException::withMessages(['frequency' => 'This recurring frequency is not supported.']),
        };
    }
}
