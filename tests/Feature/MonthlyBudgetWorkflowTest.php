<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyBudgetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_view_a_monthly_budget(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('budgets.store'), [
            'submission_token' => (string) Str::uuid(),
            'category' => 'Food',
            'amount' => '12000.00',
            'date' => '2026-11-03',
        ])->assertRedirect(route('budgets.index', ['month' => '2026-11']));

        $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-11']))
            ->assertOk()->assertSee('Food')->assertSee('12,000.00');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Food',
            'amount' => '12000.00',
            'budget_month' => '2026-11',
        ]);
    }

    public function test_replayed_budget_submission_token_does_not_create_a_duplicate(): void
    {
        $user = $this->makeUser();
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'category' => 'Transport',
            'amount' => '3000.00',
            'date' => '2026-11-01',
        ];

        $this->actingAs($user)->post(route('budgets.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('budgets.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('budgets', 1);
    }

    public function test_monthly_spending_is_scoped_by_owner_month_and_exact_category(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('other@example.test');
        $user->budgets()->create(['category' => 'Food', 'amount' => '1000.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '1200.00', 'transaction_date' => '2026-11-10']);
        $user->expenses()->create(['category' => 'Transport', 'amount' => '500.00', 'transaction_date' => '2026-11-11']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '100.00', 'transaction_date' => '2026-10-31']);
        $other->expenses()->create(['category' => 'Food', 'amount' => '900.00', 'transaction_date' => '2026-11-12']);

        $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-11']))
            ->assertOk()->assertSee('1,200.00')->assertSee('200.00')->assertSee('120.0%');
    }

    public function test_zero_spend_and_exact_budget_boundaries_are_calculated_correctly(): void
    {
        $user = $this->makeUser();
        $user->budgets()->create(['category' => 'Rent', 'amount' => '8000.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);
        $user->budgets()->create(['category' => 'Food', 'amount' => '1000.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '1000.00', 'transaction_date' => '2026-11-15']);

        $this->actingAs($user)->get(route('budgets.index', ['month' => '2026-11']))
            ->assertOk()->assertSee('100.0%')->assertSee('0.00');
    }

    public function test_duplicate_category_budget_is_rejected_without_creating_a_second_row(): void
    {
        $user = $this->makeUser();
        $user->budgets()->create(['category' => 'Food', 'amount' => '500.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);

        $this->actingAs($user)->from(route('budgets.index', ['month' => '2026-11']))
            ->post(route('budgets.store'), [
                'submission_token' => (string) Str::uuid(),
                'category' => 'Food',
                'amount' => '700.00',
                'date' => '2026-11-12',
            ])->assertRedirect()->assertSessionHasErrors('category');

        $this->assertDatabaseCount('budgets', 1);
    }

    public function test_stale_budget_update_and_delete_are_rejected(): void
    {
        $user = $this->makeUser();
        $budget = $user->budgets()->create(['category' => 'Food', 'amount' => '500.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);

        $this->actingAs($user)->put(route('budgets.update', $budget), [
            'lock_version' => 0, 'category' => 'Groceries', 'amount' => '600.00', 'date' => '2026-11-02',
        ])->assertRedirect();
        $this->actingAs($user)->put(route('budgets.update', $budget), [
            'lock_version' => 0, 'category' => 'Stale', 'amount' => '700.00', 'date' => '2026-11-02',
        ])->assertStatus(409);
        $this->actingAs($user)->delete(route('budgets.destroy', $budget), ['lock_version' => 0])->assertStatus(409);

        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'category' => 'Groceries', 'lock_version' => 1]);
    }

    public function test_copy_month_skips_existing_categories_and_reports_duplicates(): void
    {
        $user = $this->makeUser();
        $user->budgets()->create(['category' => 'Food', 'amount' => '500.00', 'budget_month' => '2026-10', 'date' => '2026-10-01']);
        $user->budgets()->create(['category' => 'Transport', 'amount' => '200.00', 'budget_month' => '2026-10', 'date' => '2026-10-01']);
        $user->budgets()->create(['category' => 'Food', 'amount' => '900.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);

        $this->actingAs($user)->post(route('budgets.copy'), [
            'from_month' => '2026-10', 'to_month' => '2026-11', 'strategy' => 'skip',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category' => 'Food', 'budget_month' => '2026-11', 'amount' => '900.00']);
        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category' => 'Transport', 'budget_month' => '2026-11', 'amount' => '200.00']);

        $this->actingAs($user)->post(route('budgets.copy'), [
            'from_month' => '2026-10', 'to_month' => '2026-11', 'strategy' => 'report',
        ])->assertRedirect()->assertSessionHasErrors('copy');
    }

    public function test_replace_copy_requires_confirmation_and_replaces_target_month_atomically(): void
    {
        $user = $this->makeUser();
        $user->budgets()->create(['category' => 'Food', 'amount' => '500.00', 'budget_month' => '2026-10', 'date' => '2026-10-01']);
        $user->budgets()->create(['category' => 'Food', 'amount' => '900.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);
        $user->budgets()->create(['category' => 'Rent', 'amount' => '5000.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);

        $this->actingAs($user)->post(route('budgets.copy'), [
            'from_month' => '2026-10', 'to_month' => '2026-11', 'strategy' => 'replace',
        ])->assertSessionHasErrors('confirm_replace');

        $this->actingAs($user)->post(route('budgets.copy'), [
            'from_month' => '2026-10', 'to_month' => '2026-11', 'strategy' => 'replace', 'confirm_replace' => '1',
        ])->assertRedirect();

        $this->assertDatabaseCount('budgets', 2);
        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category' => 'Food', 'budget_month' => '2026-11', 'amount' => '500.00']);
        $this->assertDatabaseMissing('budgets', ['user_id' => $user->id, 'category' => 'Rent', 'budget_month' => '2026-11']);
    }

    public function test_user_cannot_update_another_users_budget(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $budget = $owner->budgets()->create(['category' => 'Food', 'amount' => '500.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);

        $this->actingAs($other)->put(route('budgets.update', $budget), [
            'lock_version' => 0, 'category' => 'Unauthorized', 'amount' => '1.00', 'date' => '2026-11-01',
        ])->assertNotFound();

        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'user_id' => $owner->id, 'category' => 'Food']);
    }

    private function makeUser(string $email = 'budget@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Budget Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
