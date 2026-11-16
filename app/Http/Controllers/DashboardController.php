<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $monthStart = Carbon::now(config('app.timezone'))->startOfMonth()->toDateString();
        $monthEnd = Carbon::now(config('app.timezone'))->endOfMonth()->toDateString();

        $income = (float) $user->income()
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $expenses = (float) $user->expenses()
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $savings = (float) $user->savings()
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $recentIncome = $user->income()->latest('transaction_date')->limit(5)->get();
        $recentExpenses = $user->expenses()->latest('transaction_date')->limit(5)->get();

        return view('dashboard', [
            'monthLabel' => Carbon::now(config('app.timezone'))->format('F Y'),
            'totalIncome' => $income,
            'totalExpenses' => $expenses,
            'totalSavings' => $savings,
            'netBalance' => $income - $expenses - $savings,
            'recentIncome' => $recentIncome,
            'recentExpenses' => $recentExpenses,
        ]);
    }
}
