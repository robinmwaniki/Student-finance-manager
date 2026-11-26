<?php

namespace App\Http\Controllers;

use App\Services\MarketInformationService;
use App\Support\Money;
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

        // Load current, previous and three forecast months with four grouped
        // aggregates instead of issuing four SUM queries for each month.
        $monthlyTotals = $this->monthlyTotals($user, $monthStart->subMonths(4)->startOfMonth(), $monthEnd);
        $current = $monthlyTotals[$month] ?? $this->emptyTotals();
        $previous = $monthlyTotals[$previousStart->format('Y-m')] ?? $this->emptyTotals();

        $forecastMonths = [];
        for ($offset = 3; $offset >= 1; $offset--) {
            $period = $monthStart->subMonths($offset)->format('Y-m');
            $forecastMonths[] = $monthlyTotals[$period] ?? $this->emptyTotals();
        }
        $forecast = [];
        foreach (['income', 'expenses', 'savings', 'budget', 'balance'] as $metric) {
            $forecast[$metric] = Money::average(array_column($forecastMonths, $metric));
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
                $spentCents = Money::toMinorUnits((string) ($expenseByCategory[$budget->category] ?? '0.00'));
                $limitCents = Money::toMinorUnits((string) $budget->amount);
                $usage = $limitCents > 0 ? ($spentCents / $limitCents) * 100 : 0;

                return (object) [
                    'category' => $budget->category,
                    'budget' => Money::fromMinorUnits($limitCents),
                    'spent' => Money::fromMinorUnits($spentCents),
                    'remaining' => Money::fromMinorUnits($limitCents - $spentCents),
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

    /**
     * Return per-month financial totals for the requested range. The three
     * transaction tables and budgets are each scanned by one grouped query.
     */
    private function monthlyTotals($user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $totals = [];
        for ($month = $start->startOfMonth(); $month <= $end; $month = $month->addMonth()) {
            $totals[$month->format('Y-m')] = $this->emptyTotals();
        }

        $from = $start->toDateString();
        $to = $end->toDateString();

        foreach ([
            'income' => $user->income(),
            'expenses' => $user->expenses(),
            'savings' => $user->savings(),
        ] as $metric => $query) {
            $rows = $query
                ->whereBetween('transaction_date', [$from, $to])
                ->selectRaw("to_char(transaction_date, 'YYYY-MM') AS month, SUM(amount) AS total")
                ->groupByRaw("to_char(transaction_date, 'YYYY-MM')")
                ->get();

            foreach ($rows as $row) {
                if (array_key_exists($row->month, $totals)) {
                    $totals[$row->month][$metric] = (string) $row->total;
                }
            }
        }

        $budgetRows = $user->budgets()
            ->whereBetween('budget_month', [array_key_first($totals), array_key_last($totals)])
            ->selectRaw('budget_month AS month, SUM(amount) AS total')
            ->groupBy('budget_month')
            ->get();

        foreach ($budgetRows as $row) {
            if (array_key_exists($row->month, $totals)) {
                $totals[$row->month]['budget'] = (string) $row->total;
            }
        }

        foreach ($totals as &$monthTotals) {
            $monthTotals['balance'] = Money::subtract(
                $monthTotals['income'],
                $monthTotals['expenses'],
                $monthTotals['savings'],
            );
        }
        unset($monthTotals);

        return $totals;
    }

    private function emptyTotals(): array
    {
        return [
            'income' => '0.00',
            'expenses' => '0.00',
            'savings' => '0.00',
            'budget' => '0.00',
            'balance' => '0.00',
        ];
    }

    private function comparison(string $current, string $previous): array
    {
        $currentCents = Money::toMinorUnits($current);
        $previousCents = Money::toMinorUnits($previous);
        $difference = $currentCents - $previousCents;

        return [
            'difference' => Money::fromMinorUnits($difference),
            'percentage' => $previousCents === 0
                ? ($currentCents === 0 ? 0.0 : null)
                : ($difference / abs($previousCents)) * 100,
            'hasBaseline' => $previousCents !== 0,
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
