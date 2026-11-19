<?php

namespace Tests\Feature;

use App\Models\RecurringLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialWriteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_replayed_income_submission_token_does_not_create_a_duplicate(): void
    {
        $user = $this->makeUser();
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'source' => 'Salary',
            'amount' => '1500.00',
            'transaction_date' => now()->toDateString(),
            'tag' => 'Work',
        ];

        $this->actingAs($user)->post(route('income.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('income.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('income', 1);
        $this->assertDatabaseHas('income', [
            'user_id' => $user->id,
            'submission_token' => $payload['submission_token'],
            'amount' => '1500.00',
        ]);
    }

    public function test_replayed_expense_submission_token_does_not_create_a_duplicate(): void
    {
        $user = $this->makeUser();
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'category' => 'Transport',
            'amount' => '250.00',
            'transaction_date' => now()->toDateString(),
            'tag' => null,
        ];

        $this->actingAs($user)->post(route('expenses.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('expenses.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_stale_income_update_cannot_overwrite_a_newer_version(): void
    {
        $user = $this->makeUser();
        $record = $user->income()->create([
            'source' => 'Salary',
            'amount' => '1500.00',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->put(route('income.update', $record), [
            'lock_version' => 0,
            'source' => 'Updated salary',
            'amount' => '1600.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($user)->put(route('income.update', $record), [
            'lock_version' => 0,
            'source' => 'Stale overwrite',
            'amount' => '1700.00',
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(409);

        $this->assertDatabaseHas('income', [
            'id' => $record->id,
            'source' => 'Updated salary',
            'amount' => '1600.00',
            'lock_version' => 1,
        ]);
    }

    public function test_user_cannot_update_another_users_income_record(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $record = $owner->income()->create([
            'source' => 'Salary',
            'amount' => '1500.00',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($other)->put(route('income.update', $record), [
            'lock_version' => 0,
            'source' => 'Unauthorized',
            'amount' => '1.00',
            'transaction_date' => now()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseHas('income', [
            'id' => $record->id,
            'user_id' => $owner->id,
            'source' => 'Salary',
        ]);
    }

    public function test_database_rejects_duplicate_monthly_budgets_for_one_user_and_category(): void
    {
        $user = $this->makeUser();
        $budget = [
            'category' => 'Food',
            'amount' => '500.00',
            'budget_month' => now()->format('Y-m'),
        ];

        $user->budgets()->create($budget);

        $this->expectException(QueryException::class);
        $user->budgets()->create($budget);
    }

    public function test_database_rejects_duplicate_recurring_processing_for_the_same_period(): void
    {
        $user = $this->makeUser();
        $recurring = $user->recurringTransactions()->create([
            'amount' => '100.00',
            'type' => 'expense',
            'frequency' => 'monthly',
            'next_due' => now()->toDateString(),
        ]);
        $log = [
            'recurring_id' => $recurring->id,
            'user_id' => $user->id,
            'applied_month' => now()->format('Y-m'),
            'period_key' => 'M:'.now()->format('Y-m'),
        ];

        RecurringLog::create($log);

        $this->expectException(QueryException::class);
        RecurringLog::create($log);
    }

    public function test_stale_expense_update_cannot_overwrite_a_newer_version(): void
    {
        $user = $this->makeUser();
        $record = $user->expenses()->create([
            'category' => 'Transport',
            'amount' => '250.00',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->put(route('expenses.update', $record), [
            'lock_version' => 0,
            'category' => 'Travel',
            'amount' => '300.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($user)->put(route('expenses.update', $record), [
            'lock_version' => 0,
            'category' => 'Stale category',
            'amount' => '999.00',
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(409);

        $this->assertDatabaseHas('expenses', [
            'id' => $record->id,
            'category' => 'Travel',
            'amount' => '300.00',
            'lock_version' => 1,
        ]);
    }

    public function test_stale_expense_delete_is_rejected_after_a_concurrent_update(): void
    {
        $user = $this->makeUser();
        $record = $user->expenses()->create([
            'category' => 'Food',
            'amount' => '400.00',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->put(route('expenses.update', $record), [
            'lock_version' => 0,
            'category' => 'Groceries',
            'amount' => '450.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($user)->delete(route('expenses.destroy', $record), [
            'lock_version' => 0,
        ])->assertStatus(409);

        $this->assertDatabaseHas('expenses', [
            'id' => $record->id,
            'category' => 'Groceries',
            'lock_version' => 1,
        ]);
    }

    private function makeUser(string $email = 'finance@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Finance Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
