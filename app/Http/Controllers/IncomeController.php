<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->query('month', now(config('app.timezone'))->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 404);

        $records = $request->user()->income()
            ->whereBetween('transaction_date', [$month.'-01', now()->parse($month.'-01')->endOfMonth()->toDateString()])
            ->latest('transaction_date')
            ->paginate(15)
            ->withQueryString();

        $total = $request->user()->income()
            ->whereBetween('transaction_date', [$month.'-01', now()->parse($month.'-01')->endOfMonth()->toDateString()])
            ->sum('amount');

        return view('finance.income.index', compact('records', 'month', 'total'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->income()->create($data);

        return back()->with('success', 'Income record added.');
    }

    public function update(Request $request, int $income): RedirectResponse
    {
        $record = $request->user()->income()->findOrFail($income);
        $data = $request->validate([
            'source' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $record->update($data);

        return back()->with('success', 'Income record updated.');
    }

    public function destroy(Request $request, int $income): RedirectResponse
    {
        $request->user()->income()->findOrFail($income)->delete();

        return back()->with('success', 'Income record deleted.');
    }
}
