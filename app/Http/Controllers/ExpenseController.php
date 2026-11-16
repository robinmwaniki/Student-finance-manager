<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->query('month', now(config('app.timezone'))->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 404);
        $start = $month.'-01';
        $end = now()->parse($start)->endOfMonth()->toDateString();

        $records = $request->user()->expenses()
            ->whereBetween('transaction_date', [$start, $end])
            ->latest('transaction_date')
            ->paginate(15)
            ->withQueryString();

        $total = $request->user()->expenses()
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        return view('finance.expenses.index', compact('records', 'month', 'total'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->expenses()->create($data);

        return back()->with('success', 'Expense record added.');
    }

    public function update(Request $request, int $expense): RedirectResponse
    {
        $record = $request->user()->expenses()->findOrFail($expense);
        $data = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $record->update($data);

        return back()->with('success', 'Expense record updated.');
    }

    public function destroy(Request $request, int $expense): RedirectResponse
    {
        $request->user()->expenses()->findOrFail($expense)->delete();

        return back()->with('success', 'Expense record deleted.');
    }
}
