<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RecurringTransactionProcessor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecurringTransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_user_can_create_and_view_a_recurring_expense(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('recurring.store'), [
            'label' => 'Monthly rent',
            'type' => 'expense',
            'category' => 'Housing',
            'amount' => '12000.00',
            'frequency' => 'monthly',
            'next_due' => '2026-12-01',
            'day_of_month' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $user->id,
            'label' => 'Monthly rent',
            'type' => 'expense',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $this->actingAs($user)->get(route('recurring.index'))->assertOk()->assertSee('Monthly rent');
    }

    public function test_due_daily_expense_is_applied_once_and_advances_schedule(): void
    {
        Carbon::setTestNow('2026-11-19 10:00:00');
        $user = $this->makeUser();
        $schedule = $user->recurringTransactions()->create([
            'label' => 'Daily commute',
            'type' => 'expense',
            'category' => 'Transport',
            'amount' => '150.00',
            'frequency' => 'daily',
            'next_due' => '2026-11-19',
            'status' => 'active',
            'active' => true,
        ]);

        $this->actingAs($user)->post(route('recurring.process'), ['recurring_id' => $schedule->id])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'category' => 'Transport',
            'amount' => '150.00',
            'transaction_date' => '2026-11-19',
        ]);
        $expense = $user->expenses()->firstOrFail();
        $this->assertDatabaseHas('recurring_log', [
            'recurring_id' => $schedule->id,
            'period_key' => 'D:2026-11-19',
            'expense_id' => $expense->id,
        ]);
        $this->assertDatabaseHas('recurring_transactions', ['id' => $schedule->id, 'next_due' => '2026-11-20']);

        app(RecurringTransactionProcessor::class)->process($user->id, $schedule->id);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('recurring_log', 1);
    }

    public function test_weekly_income_catches_up_missed_periods_without_duplicates(): void
    {
        Carbon::setTestNow('2026-11-19 12:00:00');
        $user = $this->makeUser();
        $schedule = $user->recurringTransactions()->create([
            'label' => 'Weekly allowance',
            'type' => 'income',
            'amount' => '2000.00',
            'frequency' => 'weekly',
            'next_due' => '2026-11-05',
            'status' => 'active',
            'active' => true,
        ]);

        $count = app(RecurringTransactionProcessor::class)->process($user->id, $schedule->id);

        $this->assertSame(3, $count);
        $this->assertDatabaseCount('income', 3);
        $this->assertDatabaseCount('recurring_log', 3);
        $this->assertDatabaseHas('recurring_log', ['period_key' => 'W:2026-46']);
        $this->assertDatabaseHas('recurring_transactions', ['id' => $schedule->id, 'next_due' => '2026-11-26']);
    }

    public function test_monthly_schedule_clamps_short_month_and_restores_configured_day(): void
    {
        Carbon::setTestNow('2026-04-30 09:00:00');
        $user = $this->makeUser();
        $schedule = $user->recurringTransactions()->create([
            'label' => 'Monthly subscription',
            'type' => 'expense',
            'category' => 'Subscriptions',
            'amount' => '499.00',
            'frequency' => 'monthly',
            'next_due' => '2026-04-30',
            'day_of_month' => 31,
            'status' => 'active',
            'active' => true,
        ]);

        app(RecurringTransactionProcessor::class)->process($user->id, $schedule->id);

        $this->assertDatabaseHas('recurring_transactions', ['id' => $schedule->id, 'next_due' => '2026-05-31']);
        $this->assertDatabaseHas('recurring_log', ['period_key' => 'M:2026-04']);
    }

    public function test_paused_schedule_is_not_processed_and_can_be_resumed(): void
    {
        Carbon::setTestNow('2026-11-19');
        $user = $this->makeUser();
        $schedule = $user->recurringTransactions()->create([
            'type' => 'expense',
            'category' => 'Food',
            'amount' => '300.00',
            'frequency' => 'daily',
            'next_due' => '2026-11-18',
            'status' => 'active',
            'active' => true,
        ]);

        $this->actingAs($user)->patch(route('recurring.toggle', $schedule))->assertRedirect();
        $this->assertDatabaseHas('recurring_transactions', ['id' => $schedule->id, 'active' => false, 'status' => 'paused']);

        app(RecurringTransactionProcessor::class)->process($user->id);
        $this->assertDatabaseCount('expenses', 0);

        $this->actingAs($user)->patch(route('recurring.toggle', $schedule))->assertRedirect();
        app(RecurringTransactionProcessor::class)->process($user->id);
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_user_cannot_process_another_users_recurring_schedule(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $schedule = $owner->recurringTransactions()->create([
            'type' => 'income',
            'amount' => '100.00',
            'frequency' => 'daily',
            'next_due' => '2026-11-18',
            'status' => 'active',
            'active' => true,
        ]);

        $this->actingAs($other)->post(route('recurring.process'), ['recurring_id' => $schedule->id])->assertNotFound();
        $this->assertDatabaseCount('income', 0);
    }

    public function test_failed_occurrence_rolls_back_ledger_log_and_schedule_advance(): void
    {
        Carbon::setTestNow('2026-11-19');
        $user = $this->makeUser();
        $schedule = $user->recurringTransactions()->create([
            'type' => 'unsupported',
            'amount' => '100.00',
            'frequency' => 'daily',
            'next_due' => '2026-11-19',
            'status' => 'active',
            'active' => true,
        ]);

        try {
            app(RecurringTransactionProcessor::class)->process($user->id, $schedule->id);
            $this->fail('An unsupported type must not be applied.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('income', 0);
            $this->assertDatabaseCount('expenses', 0);
            $this->assertDatabaseCount('recurring_log', 0);
            $this->assertDatabaseHas('recurring_transactions', ['id' => $schedule->id, 'next_due' => '2026-11-19']);
        }
    }

    public function test_scheduled_command_processes_due_records_for_all_users(): void
    {
        Carbon::setTestNow('2026-11-19 00:05:00');
        $first = $this->makeUser('first@example.test');
        $second = $this->makeUser('second@example.test');

        foreach ([$first, $second] as $user) {
            $user->recurringTransactions()->create([
                'type' => 'expense',
                'category' => 'Utilities',
                'amount' => '75.50',
                'frequency' => 'monthly',
                'next_due' => '2026-11-19',
                'day_of_month' => 19,
                'status' => 'active',
                'active' => true,
            ]);
        }

        $this->artisan('finance:process-recurring')->assertExitCode(0);

        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseCount('recurring_log', 2);
        $this->assertDatabaseHas('expenses', ['user_id' => $first->id, 'wallet_id' => $first->wallets()->where('is_default', true)->value('id')]);
        $this->assertDatabaseHas('expenses', ['user_id' => $second->id, 'wallet_id' => $second->wallets()->where('is_default', true)->value('id')]);
    }

    public function test_recurring_command_is_registered_in_the_laravel_scheduler(): void
    {
        $scheduled = collect(Schedule::events())->first(
            fn ($event) => str_contains($event->command ?? '', 'finance:process-recurring')
        );

        $this->assertNotNull($scheduled);
        $this->assertSame('5 0 * * *', $scheduled->expression);
    }

    private function makeUser(string $email = 'recurring@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Recurring Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
