<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class FinancialTipsService
{
    /**
     * Calculate practical tips from persisted account data without an external provider.
     *
     * @return array<int, array{title: string, detail: string, level: string}>
     */
    public function forUser(User $user): array
    {
        $today = CarbonImmutable::now(config('app.timezone'));
        $from = $today->subDays(29)->toDateString();
        $to = $today->toDateString();
        $income = (float) $user->income()->whereBetween('transaction_date', [$from, $to])->sum('amount');
        $expenses = (float) $user->expenses()->whereBetween('transaction_date', [$from, $to])->sum('amount');
        $savings = (float) $user->savings()->whereBetween('transaction_date', [$from, $to])->sum('amount');
        $tips = [];

        if ($income <= 0) {
            $tips[] = [
                'title' => 'Build a clear starting picture',
                'detail' => 'Record your income consistently so your spending and savings decisions can be compared with money coming in.',
                'level' => 'info',
            ];
        } elseif ($expenses / $income >= 0.8) {
            $tips[] = [
                'title' => 'Review your spending ratio',
                'detail' => 'Your recorded expenses are at least 80% of recorded income over the last 30 days. Review flexible categories and protect essential costs first.',
                'level' => 'warning',
            ];
        }

        if ($income > 0 && $savings / $income < 0.1) {
            $tips[] = [
                'title' => 'Make saving more consistent',
                'detail' => 'Your recorded savings are below 10% of recorded income over the last 30 days. Consider a small, realistic automatic target if your essentials are covered.',
                'level' => 'info',
            ];
        }

        $monthStart = $today->startOfMonth();
        $spentByCategory = $user->expenses()
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $to])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $budgets = $user->budgets()->where('budget_month', $today->format('Y-m'))->get(['category', 'amount']);
        foreach ($budgets as $budget) {
            $limit = (float) $budget->amount;
            $spent = (float) ($spentByCategory[$budget->category] ?? 0);
            $usage = $limit > 0 ? ($spent / $limit) * 100 : 0;

            if ($usage >= 80) {
                $tips[] = [
                    'title' => $usage >= 100 ? 'Budget limit exceeded' : 'Budget getting close',
                    'detail' => $budget->category.' is at '.number_format($usage, 0).'% of its monthly limit. Check upcoming needs before making optional purchases.',
                    'level' => $usage >= 100 ? 'warning' : 'info',
                ];
            }
        }

        if ($tips === []) {
            $tips[] = [
                'title' => 'Keep your records current',
                'detail' => 'Continue recording income, expenses and savings. Consistent records make your monthly comparisons and budget decisions more useful.',
                'level' => 'success',
            ];
        }

        return array_slice($tips, 0, 5);
    }
}
