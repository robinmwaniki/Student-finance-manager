<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_month_summaries_include_income_expenses_savings_and_budget(): void
    {
        $user = $this->makeUser();
        $user->income()->create(['source' => 'Salary', 'amount' => '50000.00', 'transaction_date' => '2026-11-05']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '12000.00', 'transaction_date' => '2026-11-10']);
        $user->savings()->create(['goal' => 'Emergency', 'amount' => '5000.00', 'transaction_date' => '2026-11-12', 'savings_month' => '2026-11']);
        $user->budgets()->create(['category' => 'Food', 'amount' => '15000.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);

        $this->actingAs($user)->get(route('dashboard', ['month' => '2026-11']))
            ->assertOk()
            ->assertViewHas('totalIncome', 50000.0)
            ->assertViewHas('totalExpenses', 12000.0)
            ->assertViewHas('totalSavings', 5000.0)
            ->assertViewHas('totalBudget', 15000.0)
            ->assertViewHas('netBalance', 33000.0)
            ->assertSee('November 2026');
    }

    public function test_month_boundaries_and_expense_categories_are_scoped_to_selected_month_and_owner(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('other@example.test');
        $user->expenses()->create(['category' => 'Food', 'amount' => '100.00', 'transaction_date' => '2026-11-01']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '200.00', 'transaction_date' => '2026-11-30']);
        $user->expenses()->create(['category' => 'Travel', 'amount' => '50.00', 'transaction_date' => '2026-10-31']);
        $other->expenses()->create(['category' => 'Food', 'amount' => '999.00', 'transaction_date' => '2026-11-15']);

        $this->actingAs($user)->get(route('dashboard', ['month' => '2026-11']))
            ->assertOk()
            ->assertViewHas('totalExpenses', 300.0)
            ->assertViewHas('categoryBreakdown', fn ($rows) => $rows->count() === 1 && (float) $rows->first()->total === 300.0);
    }

    public function test_comparison_handles_a_zero_previous_month_baseline(): void
    {
        $user = $this->makeUser();
        $user->income()->create(['source' => 'First salary', 'amount' => '1000.00', 'transaction_date' => '2026-11-02']);

        $this->actingAs($user)->get(route('dashboard', ['month' => '2026-11']))
            ->assertOk()
            ->assertViewHas('comparison.income.percentage', null)
            ->assertViewHas('comparison.income.hasBaseline', false);
    }

    public function test_forecast_is_the_average_of_three_complete_preceding_months(): void
    {
        $user = $this->makeUser();
        $user->income()->create(['source' => 'August', 'amount' => '3000.00', 'transaction_date' => '2026-08-10']);
        $user->income()->create(['source' => 'September', 'amount' => '6000.00', 'transaction_date' => '2026-09-10']);
        $user->income()->create(['source' => 'October', 'amount' => '9000.00', 'transaction_date' => '2026-10-10']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '300.00', 'transaction_date' => '2026-08-12']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '600.00', 'transaction_date' => '2026-10-12']);

        $this->actingAs($user)->get(route('dashboard', ['month' => '2026-11']))
            ->assertOk()
            ->assertViewHas('forecast.income', 6000.0)
            ->assertViewHas('forecast.expenses', 300.0)
            ->assertViewHas('forecast.balance', 5700.0)
            ->assertViewHas('forecastBasis', 'Aug 2026 – Oct 2026');
    }

    public function test_budget_alerts_include_near_limit_and_over_budget_categories(): void
    {
        $user = $this->makeUser();
        $user->budgets()->create(['category' => 'Food', 'amount' => '1000.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);
        $user->budgets()->create(['category' => 'Transport', 'amount' => '500.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);
        $user->budgets()->create(['category' => 'Rent', 'amount' => '10000.00', 'budget_month' => '2026-11', 'date' => '2026-11-01']);
        $user->expenses()->create(['category' => 'Food', 'amount' => '800.00', 'transaction_date' => '2026-11-10']);
        $user->expenses()->create(['category' => 'Transport', 'amount' => '600.00', 'transaction_date' => '2026-11-10']);

        $this->actingAs($user)->get(route('dashboard', ['month' => '2026-11']))
            ->assertOk()
            ->assertViewHas('budgetAlerts', fn ($alerts) => $alerts->count() === 2
                && $alerts->contains(fn ($item) => $item->category === 'Food' && $item->level === 'warning')
                && $alerts->contains(fn ($item) => $item->category === 'Transport' && $item->level === 'exceeded'));
    }

    public function test_empty_dashboard_has_zero_totals_and_a_complete_daily_series(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('dashboard', ['month' => '2026-02']))
            ->assertOk()
            ->assertViewHas('totalIncome', 0.0)
            ->assertViewHas('totalExpenses', 0.0)
            ->assertViewHas('categoryBreakdown', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('dailyLabels', fn ($labels) => count($labels) === 28)
            ->assertViewHas('recentActivity', fn ($activity) => $activity->isEmpty());
    }

    public function test_invalid_month_selection_is_rejected(): void
    {
        $this->actingAs($this->makeUser())->get(route('dashboard', ['month' => '2026-13']))->assertNotFound();
    }

    private function makeUser(string $email = 'dashboard@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Dashboard Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
