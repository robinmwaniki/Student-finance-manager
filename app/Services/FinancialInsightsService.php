<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class FinancialInsightsService
{
    /**
     * Return explainable, provider-free insights calculated only from this account.
     *
     * @return array{period:string,income:float,expenses:float,savings:float,net:float,top_categories:array<int,array{category:string,amount:float,share:float}>,recommendations:array<int,array{title:string,detail:string,level:string}>}
     */
    public function forUser(User $user): array
    {
        $today = CarbonImmutable::now(config('app.timezone'));
        $start = $today->startOfMonth()->toDateString();
        $end = $today->toDateString();
        $previousStart = $today->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousEnd = $today->subMonthNoOverflow()->endOfMonth()->min($today->subDay())->toDateString();

        $income = (float) $user->income()->whereBetween('transaction_date', [$start, $end])->sum('amount');
        $expenses = (float) $user->expenses()->whereBetween('transaction_date', [$start, $end])->sum('amount');
        $savings = (float) $user->savings()->whereBetween('transaction_date', [$start, $end])->sum('amount');
        $previousExpenses = (float) $user->expenses()->whereBetween('transaction_date', [$previousStart, $previousEnd])->sum('amount');

        $byCategory = $user->expenses()
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topCategories = $byCategory->map(fn ($row): array => [
            'category' => (string) $row->category,
            'amount' => (float) $row->total,
            'share' => $expenses > 0 ? round(((float) $row->total / $expenses) * 100, 1) : 0.0,
        ])->all();

        $recommendations = [];
        if ($income <= 0 && $expenses > 0) {
            $recommendations[] = [
                'title' => 'Record your income',
                'detail' => 'You have recorded expenses but no income this month. Add income to make the savings and affordability suggestions more reliable.',
                'level' => 'info',
            ];
        }

        if ($income > 0 && $expenses > $income) {
            $recommendations[] = [
                'title' => 'Spending is higher than income',
                'detail' => 'Recorded expenses exceed recorded income by KES '.number_format($expenses - $income, 2).'. Review optional purchases first and check whether all income has been recorded.',
                'level' => 'warning',
            ];
        } elseif ($income > 0 && $expenses / $income >= 0.8) {
            $recommendations[] = [
                'title' => 'Protect room in your budget',
                'detail' => 'Recorded expenses use '.number_format(($expenses / $income) * 100, 1).'% of this month’s recorded income. Review flexible spending before increasing any budget.',
                'level' => 'warning',
            ];
        }

        if ($previousExpenses > 0 && $expenses > $previousExpenses * 1.1) {
            $increase = (($expenses - $previousExpenses) / $previousExpenses) * 100;
            $recommendations[] = [
                'title' => 'Expenses have increased',
                'detail' => 'This month’s expenses are '.number_format($increase, 1).'% higher than the previous month-to-date comparison. Check the category breakdown for the main driver.',
                'level' => 'info',
            ];
        } elseif ($previousExpenses > 0 && $expenses < $previousExpenses * 0.9) {
            $recommendations[] = [
                'title' => 'Spending is trending down',
                'detail' => 'Recorded expenses are lower than the previous month-to-date comparison. Keep the changes that are working while protecting essential spending.',
                'level' => 'success',
            ];
        }

        $budgets = $user->budgets()->where('budget_month', $today->format('Y-m'))->get(['category', 'amount', 'limit_amount']);
        foreach ($budgets as $budget) {
            $limit = (float) ($budget->amount ?: $budget->limit_amount);
            if ($limit <= 0) {
                continue;
            }
            $spent = (float) ($byCategory->firstWhere('category', $budget->category)?->total ?? 0);
            $usage = ($spent / $limit) * 100;
            if ($usage >= 100) {
                $recommendations[] = [
                    'title' => 'Review the '.$budget->category.' budget',
                    'detail' => $budget->category.' has used '.number_format($usage, 1).'% of its KES '.number_format($limit, 2).' limit. Consider reducing optional purchases; only raise the limit if your income and priorities support it.',
                    'level' => 'warning',
                ];
            } elseif ($usage >= 80) {
                $recommendations[] = [
                    'title' => $budget->category.' budget is nearly used',
                    'detail' => $budget->category.' has used '.number_format($usage, 1).'% of its monthly limit. Check upcoming essentials before spending more.',
                    'level' => 'info',
                ];
            }
        }

        if ($income > 0 && $savings < $income * 0.1 && $expenses < $income) {
            $room = max(0, $income - $expenses);
            $suggestion = min($room, max(0, $income * 0.05));
            if ($suggestion > 0) {
                $recommendations[] = [
                    'title' => 'Consider a small savings target',
                    'detail' => 'Based on the records entered, you may have room to set aside about KES '.number_format($suggestion, 2).' this month. Treat this as an optional target after essential bills and debt obligations.',
                    'level' => 'success',
                ];
            }
        }

        if ($recommendations === []) {
            $recommendations[] = [
                'title' => 'Keep your financial records up to date',
                'detail' => 'Continue recording transactions and reviewing your category totals. More complete records make these suggestions more useful.',
                'level' => 'info',
            ];
        }

        return [
            'period' => $today->format('F Y'),
            'income' => $income,
            'expenses' => $expenses,
            'savings' => $savings,
            'net' => $income - $expenses - $savings,
            'top_categories' => $topCategories,
            'recommendations' => array_slice($recommendations, 0, 6),
        ];
    }
}
