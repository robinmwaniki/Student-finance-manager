<?php

namespace App\Http\Controllers;

use App\Services\MarketInformationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, MarketInformationService $market): View
    {
        $month = $request->query('month', now(config('app.timezone'))->format('Y-m'));
        abort_unless(is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1, 404);

        $user = $request->user();
        $dashboardPreferences = array_merge([
            'show_daily_quote' => true,
            'show_weather' => true,
            'show_forecast' => true,
        ], $user->dashboard_preferences ?? []);
        $monthStart = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $previousStart = $monthStart->subMonth()->startOfMonth();
        $previousEnd = $previousStart->endOfMonth();

        $current = $this->totals($user, $monthStart, $monthEnd);
        $previous = $this->totals($user, $previousStart, $previousEnd);
        $forecastMonths = [];
        for ($offset = 3; $offset >= 1; $offset--) {
            $periodStart = $monthStart->subMonths($offset)->startOfMonth();
            $forecastMonths[] = $this->totals($user, $periodStart, $periodStart->endOfMonth());
        }
        $forecast = [];
        foreach (['income', 'expenses', 'savings', 'budget', 'balance'] as $metric) {
            $forecast[$metric] = array_sum(array_column($forecastMonths, $metric)) / 3;
        }

        $daysInMonth = $monthStart->daysInMonth;
        $dailyIncome = $this->dailySeries($user->income(), $monthStart, $monthEnd, $daysInMonth);
        $dailyExpenses = $this->dailySeries($user->expenses(), $monthStart, $monthEnd, $daysInMonth);
        $dailySavings = $this->dailySeries($user->savings(), $monthStart, $monthEnd, $daysInMonth);

        $categoryBreakdown = $user->expenses()
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $expenseByCategory = $categoryBreakdown->pluck('total', 'category');
        $budgetAlerts = $user->budgets()->where('budget_month', $month)->orderBy('category')->get()
            ->map(function ($budget) use ($expenseByCategory): object {
                $spent = (float) ($expenseByCategory[$budget->category] ?? 0);
                $limit = (float) $budget->amount;
                $usage = $limit > 0 ? ($spent / $limit) * 100 : 0;

                return (object) [
                    'category' => $budget->category,
                    'budget' => $limit,
                    'spent' => $spent,
                    'remaining' => $limit - $spent,
                    'usage' => $usage,
                    'level' => $usage > 100 ? 'exceeded' : ($usage >= 80 ? 'warning' : 'normal'),
                ];
            })
            ->filter(fn ($item): bool => $item->level !== 'normal')
            ->values();

        $recentActivity = $this->recentActivity($user);

        return view('dashboard', [
            'month' => $month,
            'dashboardPreferences' => $dashboardPreferences,
            'dailyQuote' => $market->dailyQuote(),
            'monthLabel' => $monthStart->format('F Y'),
            'previousMonthLabel' => $previousStart->format('F Y'),
            'nextMonthLabel' => $monthStart->addMonth()->format('F Y'),
            'totalIncome' => $current['income'],
            'totalExpenses' => $current['expenses'],
            'totalSavings' => $current['savings'],
            'totalBudget' => $current['budget'],
            'netBalance' => $current['balance'],
            'previous' => $previous,
            'comparison' => [
                'income' => $this->comparison($current['income'], $previous['income']),
                'expenses' => $this->comparison($current['expenses'], $previous['expenses']),
                'savings' => $this->comparison($current['savings'], $previous['savings']),
                'budget' => $this->comparison($current['budget'], $previous['budget']),
                'balance' => $this->comparison($current['balance'], $previous['balance']),
            ],
            'forecast' => $forecast,
            'forecastBasis' => $monthStart->subMonths(3)->format('M Y').' – '.$monthStart->subMonth()->format('M Y'),
            'forecastMonthLabel' => $monthStart->addMonth()->format('F Y'),
            'dailyLabels' => range(1, $daysInMonth),
            'dailyIncome' => $dailyIncome,
            'dailyExpenses' => $dailyExpenses,
            'dailySavings' => $dailySavings,
            'categoryBreakdown' => $categoryBreakdown,
            'budgetAlerts' => $budgetAlerts,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Aggregate chart values in the database so the dashboard hydrates at most
     * one row per calendar day instead of every financial transaction.
     */
    private function dailySeries($query, CarbonImmutable $start, CarbonImmutable $end, int $daysInMonth): array
    {
        $series = array_fill(0, $daysInMonth, 0.0);

        $dailyTotals = $query
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('EXTRACT(DAY FROM transaction_date)::integer AS day, SUM(amount) AS total')
            ->groupByRaw('EXTRACT(DAY FROM transaction_date)')
            ->orderByRaw('EXTRACT(DAY FROM transaction_date)')
            ->get();

        foreach ($dailyTotals as $dailyTotal) {
            $series[(int) $dailyTotal->day - 1] = (float) $dailyTotal->total;
        }

        return $series;
    }

    private function totals($user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $from = $start->toDateString();
        $to = $end->toDateString();
        $income = (float) $user->income()->whereBetween('transaction_date', [$from, $to])->sum('amount');
        $expenses = (float) $user->expenses()->whereBetween('transaction_date', [$from, $to])->sum('amount');
        $savings = (float) $user->savings()->whereBetween('transaction_date', [$from, $to])->sum('amount');
        $budget = (float) $user->budgets()->where('budget_month', $start->format('Y-m'))->sum('amount');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'savings' => $savings,
            'budget' => $budget,
            'balance' => $income - $expenses - $savings,
        ];
    }

    private function comparison(float $current, float $previous): array
    {
        return [
            'difference' => $current - $previous,
            'percentage' => $previous == 0.0
                ? ($current == 0.0 ? 0.0 : null)
                : (($current - $previous) / abs($previous)) * 100,
            'hasBaseline' => $previous != 0.0,
        ];
    }

    private function recentActivity($user): Collection
    {
        $income = $user->income()->latest('transaction_date')->limit(8)->get()
            ->map(fn ($item): array => [
                'type' => 'Income',
                'label' => $item->source,
                'amount' => (float) $item->amount,
                'date' => $item->transaction_date,
            ]);
        $expenses = $user->expenses()->latest('transaction_date')->limit(8)->get()
            ->map(fn ($item): array => [
                'type' => 'Expense',
                'label' => $item->category,
                'amount' => (float) $item->amount,
                'date' => $item->transaction_date,
            ]);
        $savings = $user->savings()->latest('transaction_date')->limit(8)->get()
            ->map(fn ($item): array => [
                'type' => 'Savings',
                'label' => $item->goal,
                'amount' => (float) $item->amount,
                'date' => $item->transaction_date,
            ]);

        return $income->concat($expenses)->concat($savings)
            ->sortByDesc(fn (array $item): string => $item['date']->toDateString())
            ->take(8)->values();
    }
}
