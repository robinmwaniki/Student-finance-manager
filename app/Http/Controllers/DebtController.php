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

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = $user->debts()->withSum('repayments', 'amount');
        $type = $request->query('type');
        $status = $request->query('status');
        $today = now(config('app.timezone'))->toDateString();

        if (in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        } elseif ($type !== null && $type !== '') {
            abort(404);
        }

        if ($status === 'overdue') {
            $query->whereIn('status', ['pending', 'partially_paid'])->whereNotNull('due_date')->whereDate('due_date', '<', $today);
        } elseif (in_array($status, ['pending', 'partially_paid', 'paid'], true)) {
            $query->where('status', $status);
        } elseif ($status !== null && $status !== '' && $status !== 'all') {
            abort(404);
        }

        $debts = $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();
        $borrowed = $user->debts()->where('type', 'borrowed')->get();
        $lent = $user->debts()->where('type', 'lent')->get();
        $outstanding = fn ($rows): string => number_format($rows->sum(fn ($debt) => $debt->outstandingBalanceCents()) / 100, 2, '.', '');

        return view('finance.debts.index', [
            'debts' => $debts,
            'type' => $type,
            'status' => $status ?? 'all',
            'submissionToken' => (string) Str::uuid(),
            'summary' => [
                'borrowed' => $outstanding($borrowed),
                'lent' => $outstanding($lent),
                'overdueCount' => $user->debts()->whereIn('status', ['pending', 'partially_paid'])->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
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

    public function repay(Request $request, int $debt): RedirectResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'repayment_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $overpayment = false;

        DB::transaction(function () use ($request, $debt, $data, &$overpayment): void {
            $record = $request->user()->debts()->whereKey($debt)->lockForUpdate()->firstOrFail();

            if ($request->user()->debtRepayments()->where('submission_token', $data['submission_token'])->exists()) {
                return;
            }

            $paidCents = $this->toCents((string) $record->repayments()->sum('amount'));
            $amountCents = $this->toCents((string) $data['amount']);
            $originalCents = $this->toCents((string) $record->amount);

            if ($amountCents > $originalCents - $paidCents) {
                $overpayment = true;

                return;
            }

            $record->repayments()->create([
                'user_id' => $request->user()->id,
                'amount' => $data['amount'],
                'repayment_date' => $data['repayment_date'],
                'notes' => $data['notes'] ?? null,
                'submission_token' => $data['submission_token'],
            ]);

            $record->status = ($paidCents + $amountCents) >= $originalCents ? 'paid' : 'partially_paid';
            $record->lock_version++;
            $record->save();
        }, 3);

        if ($overpayment) {
            return back()->withErrors(['amount' => 'Repayment exceeds the outstanding balance.']);
        }

        return back()->with('success', 'Repayment recorded.');
    }

    public function update(Request $request, int $debt): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'type' => ['required', Rule::in(self::TYPES)],
            'due_date' => ['nullable', 'date'],
        ]);
        $user = $request->user();
        $record = $user->debts()->whereKey($debt)->lockForUpdate()->firstOrFail();
        $paidCents = $this->toCents((string) $record->repayments()->sum('amount'));
        abort_if($record->status === 'paid', 422, 'A settled debt cannot be edited.');
        abort_if($this->toCents((string) $data['amount']) < $paidCents, 422, 'Debt amount cannot be lower than repayments already recorded.');
        $updated = $user->debts()->whereKey($debt)->where('lock_version', (int) $data['lock_version'])
            ->update([...$data, 'status' => $paidCents > 0 ? 'partially_paid' : 'pending', 'lock_version' => DB::raw('lock_version + 1')]);
        abort_unless($updated === 1, 409, 'This debt record changed after you opened it. Refresh and try again.');

        return back()->with('success', 'Debt record updated.');
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public function destroy(Request $request, int $debt): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $user = $request->user();
        $record = $user->debts()->whereKey($debt)->lockForUpdate()->firstOrFail();
        abort_if($record->repayments()->exists(), 422, 'A debt with repayment history cannot be deleted.');
        $deleted = $user->debts()->whereKey($debt)->where('lock_version', (int) $data['lock_version'])->delete();
        abort_unless($deleted === 1, 409, 'This debt record changed after you opened it. Refresh before deleting it.');

        return back()->with('success', 'Debt record deleted.');
    }
}
