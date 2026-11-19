<?php

namespace App\Console\Commands;

use App\Models\BillReminder;
use App\Models\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GenerateBillReminderNotifications extends Command
{
    protected $signature = 'finance:generate-reminders';

    protected $description = 'Generate idempotent due-soon and overdue bill reminder notifications';

    public function handle(): int
    {
        $timezone = config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $reminderIds = BillReminder::query()
            ->where('status', 'active')
            ->whereDate('due_date', '<=', $today->addDays(3)->toDateString())
            ->orderBy('due_date')
            ->pluck('id');

        $created = 0;
        foreach ($reminderIds as $id) {
            $created += DB::transaction(function () use ($id, $today, $timezone): int {
                $reminder = BillReminder::query()->whereKey($id)->lockForUpdate()->first();
                if (! $reminder || $reminder->status !== 'active') {
                    return 0;
                }

                $notificationsCreated = 0;
                for ($occurrence = 0; $occurrence < 366; $occurrence++) {
                    $due = CarbonImmutable::parse($reminder->due_date->toDateString(), $timezone)->startOfDay();

                    if ($due->greaterThan($today->addDays(3))) {
                        break;
                    }

                    if ($due->greaterThanOrEqualTo($today)) {
                        $notificationsCreated += $this->notify($reminder, $due, 'due_soon', $timezone) ? 1 : 0;
                        break;
                    }

                    $notificationsCreated += $this->notify($reminder, $due, 'overdue', $timezone) ? 1 : 0;
                    $next = $this->nextDue($due, $reminder->recurrence, (int) $reminder->due_day, (int) $reminder->due_month);

                    if ($next === null) {
                        $reminder->status = 'completed';
                        $reminder->save();
                        break;
                    }

                    $reminder->due_date = $next->toDateString();
                    $reminder->save();
                }

                return $notificationsCreated;
            }, 3);
        }

        $this->info("Created {$created} bill reminder notification(s).");

        return self::SUCCESS;
    }

    private function notify(BillReminder $reminder, CarbonImmutable $due, string $kind, string $timezone): bool
    {
        $date = $due->toDateString();
        $key = "bill-reminder:{$reminder->id}:{$date}:{$kind}";
        $message = $kind === 'overdue'
            ? "{$reminder->title} is overdue (due {$due->format('d M Y')}). Amount: KES ".number_format((float) $reminder->amount, 2).'.'
            : "{$reminder->title} is due on {$due->format('d M Y')}. Amount: KES ".number_format((float) $reminder->amount, 2).'.';

        try {
            $notification = UserNotification::query()->firstOrCreate(
                ['user_id' => $reminder->user_id, 'deduplication_key' => $key],
                ['message' => $message, 'status' => 'unread', 'type' => 'bill_reminder', 'created_at' => now($timezone)]
            );

            return $notification->wasRecentlyCreated;
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                return false;
            }

            throw $exception;
        }
    }

    private function nextDue(CarbonImmutable $due, string $recurrence, int $anchorDay, int $anchorMonth): ?CarbonImmutable
    {
        return match ($recurrence) {
            'weekly' => $due->addWeek(),
            'monthly' => $due->startOfMonth()->addMonth()->day(min($anchorDay, $due->startOfMonth()->addMonth()->daysInMonth)),
            'yearly' => CarbonImmutable::create($due->year + 1, $anchorMonth, 1)->day(min($anchorDay, CarbonImmutable::create($due->year + 1, $anchorMonth, 1)->daysInMonth)),
            'once' => null,
            default => throw new \InvalidArgumentException('Unsupported bill reminder recurrence.'),
        };
    }
}
