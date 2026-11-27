<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringTransactionProcessor
{
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
        $query
            ->reorder()
            ->chunkById(100, function ($schedules) use ($userId, &$processed): void {
                foreach ($schedules as $schedule) {
                    // Limit catch-up to one year per schedule per invocation.
                    for ($attempt = 0; $attempt < 366; $attempt++) {
                        if (! $this->processNextOccurrence($userId, (int) $schedule->id)) {
                            break;
                        }
                        $processed++;
                    }
                }
            }, 'id');

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

            if (! $schedule->logs()->where('period_key', $periodKey)->exists()) {
                $wallet = $schedule->user->wallets()->where('is_default', true)->first()
                    ?? $schedule->user->wallets()->firstOrCreate(
                        ['name' => 'Main wallet'],
                        ['type' => 'cash', 'currency' => 'KES', 'opening_balance' => 0, 'is_default' => true],
                    );

                $ledger = [
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
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
