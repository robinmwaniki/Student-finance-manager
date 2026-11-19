<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DebtController extends Controller
{
    private const TYPES = ['borrowed', 'lent'];

    private const STATUSES = ['pending', 'paid'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = $user->debts();
        $type = $request->query('type');
        $status = $request->query('status');
        $today = now(config('app.timezone'))->toDateString();

        if (in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        } elseif ($type !== null && $type !== '') {
            abort(404);
        }

        if ($status === 'overdue') {
            $query->where('status', 'pending')->whereNotNull('due_date')->whereDate('due_date', '<', $today);
        } elseif (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        } elseif ($status !== null && $status !== '' && $status !== 'all') {
            abort(404);
        }

        $debts = $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')->latest('created_at')->paginate(15)->withQueryString();

        return view('finance.debts.index', [
            'debts' => $debts,
            'type' => $type,
            'status' => $status ?? 'all',
            'submissionToken' => (string) Str::uuid(),
            'summary' => [
                'borrowed' => $user->debts()->where('type', 'borrowed')->where('status', 'pending')->sum('amount'),
                'lent' => $user->debts()->where('type', 'lent')->where('status', 'pending')->sum('amount'),
                'overdueCount' => $user->debts()->where('status', 'pending')->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'type' => ['required', Rule::in(self::TYPES)],
            'due_date' => ['nullable', 'date'],
        ]);

        $token = $data['submission_token'];
        unset($data['submission_token']);
        $request->user()->debts()->firstOrCreate(['submission_token' => $token], [...$data, 'status' => 'pending']);

        return back()->with('success', 'Debt record saved.');
    }

    public function update(Request $request, int $debt): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'type' => ['required', Rule::in(self::TYPES)],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $user = $request->user();
        $record = $user->debts()->whereKey($debt)->firstOrFail();

        // Paid debts are terminal. Correcting an accidental payment should be an audited,
        // separate adjustment rather than silently reopening a settled liability/receivable.
        abort_if($record->status === 'paid' && $data['status'] !== 'paid', 422, 'A paid debt cannot be reopened.');
        $version = (int) $data['lock_version'];
        unset($data['lock_version']);

        $updated = $user->debts()->whereKey($debt)->where('lock_version', $version)
            ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);

        abort_unless($updated === 1, 409, 'This debt record changed after you opened it. Refresh and try again.');

        return back()->with('success', 'Debt record updated.');
    }

    public function destroy(Request $request, int $debt): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $user = $request->user();
        $user->debts()->whereKey($debt)->firstOrFail();

        $deleted = $user->debts()->whereKey($debt)->where('lock_version', (int) $data['lock_version'])->delete();
        abort_unless($deleted === 1, 409, 'This debt record changed after you opened it. Refresh before deleting it.');

        return back()->with('success', 'Debt record deleted.');
    }
}
