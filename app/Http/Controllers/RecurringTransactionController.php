<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecurringTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $records = $request->user()->recurringTransactions()->withCount('logs')->withMax('logs', 'applied_at')
            ->orderByDesc('active')->orderBy('next_due')->paginate(15);

        return view('finance.recurring.index', ['records' => $records]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:150'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['nullable', 'string', 'max:50', 'required_if:type,expense'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'next_due' => ['required', 'date'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
        ]);

        $data['day_of_month'] = $data['frequency'] === 'monthly'
            ? (int) ($data['day_of_month'] ?? date('j', strtotime($data['next_due'])))
            : 1;
        $data['status'] = 'active';
        $data['active'] = true;
        $request->user()->recurringTransactions()->create($data);

        return back()->with('success', 'Recurring transaction created.');
    }

    public function update(Request $request, int $recurring): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:150'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['nullable', 'string', 'max:50', 'required_if:type,expense'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'next_due' => ['required', 'date'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
        ]);

        $record = $request->user()->recurringTransactions()->whereKey($recurring)->firstOrFail();
        abort_unless($record->active, 422, 'Resume this schedule before editing it.');
        $data['day_of_month'] = $data['frequency'] === 'monthly'
            ? (int) ($data['day_of_month'] ?? date('j', strtotime($data['next_due'])))
            : 1;

        // Editing a schedule changes only future occurrences; existing ledger rows
        // and recurring logs remain as an immutable record of what was applied.
        $record->update($data);

        return back()->with('success', 'Recurring transaction updated.');
    }

    public function toggle(Request $request, int $recurring): RedirectResponse
    {
        $record = $request->user()->recurringTransactions()->whereKey($recurring)->firstOrFail();
        $active = !$record->active;
        $record->forceFill(['active' => $active, 'status' => $active ? 'active' : 'paused'])->save();

        return back()->with('success', $active ? 'Recurring transaction resumed.' : 'Recurring transaction paused.');
    }

    public function destroy(Request $request, int $recurring): RedirectResponse
    {
        $record = $request->user()->recurringTransactions()->whereKey($recurring)->firstOrFail();
        abort_if($record->logs()->exists(), 422, 'A schedule with processing history cannot be deleted. Pause it instead to preserve its audit trail.');
        $record->delete();

        return back()->with('success', 'Recurring transaction deleted.');
    }

    public function process(Request $request, RecurringTransactionProcessor $processor): RedirectResponse
    {
        $data = $request->validate(['recurring_id' => ['nullable', 'integer', 'min:1']]);
        if (isset($data['recurring_id'])) {
            $request->user()->recurringTransactions()->whereKey($data['recurring_id'])->firstOrFail();
        }

        $count = $processor->process($request->user()->id, $data['recurring_id'] ?? null);

        return back()->with('success', $count.' due recurring occurrence(s) processed.');
    }
}
