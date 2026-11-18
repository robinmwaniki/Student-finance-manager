<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IncomeController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->query('month', now(config('app.timezone'))->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 404);

        $start = $month.'-01';
        $end = now()->parse($start)->endOfMonth()->toDateString();
        $records = $request->user()->income()
            ->whereBetween('transaction_date', [$start, $end])
            ->latest('transaction_date')
            ->paginate(15)
            ->withQueryString();

        $total = $request->user()->income()
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        return view('finance.income.index', [
            'records' => $records,
            'month' => $month,
            'total' => $total,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'source' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $token = $data['submission_token'];
        unset($data['submission_token']);

        $request->user()->income()->firstOrCreate(['submission_token' => $token], $data);

        return back()->with('success', 'Income record saved.');
    }

    public function update(Request $request, int $income): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'source' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $expectedVersion = (int) $data['lock_version'];
        unset($data['lock_version']);

        $updated = $request->user()->income()
            ->whereKey($income)
            ->where('lock_version', $expectedVersion)
            ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);

        abort_unless($updated === 1, 409, 'This income record changed after you opened it. Refresh the page and apply your changes again.');

        return back()->with('success', 'Income record updated.');
    }

    public function destroy(Request $request, int $income): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);

        $deleted = $request->user()->income()
            ->whereKey($income)
            ->where('lock_version', (int) $data['lock_version'])
            ->delete();

        abort_unless($deleted === 1, 409, 'This income record changed after you opened it. Refresh the page before deleting it.');

        return back()->with('success', 'Income record deleted.');
    }
}
