<?php

namespace App\Http\Controllers;

use App\Models\SavingsGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SavingsController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->query('month', now(config('app.timezone'))->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 404);
        $start = $month.'-01';
        $end = now()->parse($start)->endOfMonth()->toDateString();
        $user = $request->user();

        return view('finance.savings.index', [
            'records' => $user->savings()->with('savingsGoal')->whereBetween('transaction_date', [$start, $end])
                ->latest('transaction_date')->paginate(15)->withQueryString(),
            'goals' => $user->savingsGoals()->withSum('contributions', 'amount')->orderBy('due_date')->get(),
            'month' => $month,
            'monthlyTotal' => $user->savings()->whereBetween('transaction_date', [$start, $end])->sum('amount'),
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function storeGoal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'target_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $request->user()->savingsGoals()->create($data);
        return back()->with('success', 'Savings goal created.');
    }

    public function updateGoal(Request $request, int $goal): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:100'],
            'target_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'completed'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $request->user();
        $user->savingsGoals()->whereKey($goal)->firstOrFail();
        $version = (int) $data['lock_version'];
        unset($data['lock_version']);
        $updated = $user->savingsGoals()->whereKey($goal)->where('lock_version', $version)
            ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);
        abort_unless($updated === 1, 409, 'This savings goal changed after you opened it. Refresh and try again.');
        return back()->with('success', 'Savings goal updated.');
    }

    public function destroyGoal(Request $request, int $goal): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $user = $request->user();
        $user->savingsGoals()->whereKey($goal)->firstOrFail();
        DB::transaction(function () use ($user, $goal, $data): void {
            $deleted = $user->savingsGoals()->whereKey($goal)->where('lock_version', (int) $data['lock_version'])->delete();
            abort_unless($deleted === 1, 409, 'This savings goal changed after you opened it. Refresh before deleting it.');
            // Contributions survive goal deletion; the nullable foreign key is set to null.
        });
        return back()->with('success', 'Savings goal deleted. Its contribution history was retained.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'goal_id' => ['nullable', 'integer', Rule::exists('savings_goals', 'id')->where('user_id', $request->user()->id)],
            'goal' => ['nullable', 'string', 'max:100', 'required_without:goal_id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $token = $data['submission_token'];
        unset($data['submission_token']);
        $goalId = $data['goal_id'] ?? null;
        $goal = $goalId ? $request->user()->savingsGoals()->whereKey($goalId)->firstOrFail() : null;

        DB::transaction(function () use ($request, $data, $token, $goal): void {
            $request->user()->savings()->firstOrCreate(['submission_token' => $token], [
                'goal_id' => $goal?->id,
                'goal' => $goal?->title ?? $data['goal'],
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'],
                'savings_month' => substr($data['transaction_date'], 0, 7),
            ]);
            if ($goal) {
                $this->syncGoal($goal);
            }
        });
        return back()->with('success', 'Savings contribution saved.');
    }

    public function update(Request $request, int $saving): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'goal_id' => ['nullable', 'integer', Rule::exists('savings_goals', 'id')->where('user_id', $request->user()->id)],
            'goal' => ['nullable', 'string', 'max:100', 'required_without:goal_id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $user = $request->user();
        $record = $user->savings()->whereKey($saving)->firstOrFail();
        $goalId = $data['goal_id'] ?? null;
        $goal = $goalId ? $user->savingsGoals()->whereKey($goalId)->firstOrFail() : null;
        $oldGoalId = $record->goal_id;

        DB::transaction(function () use ($user, $record, $goal, $data, $oldGoalId): void {
            $updated = $user->savings()->whereKey($record->id)->where('lock_version', (int) $data['lock_version'])->update([
                'goal_id' => $goal?->id,
                'goal' => $goal?->title ?? $data['goal'],
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'],
                'savings_month' => substr($data['transaction_date'], 0, 7),
                'lock_version' => DB::raw('lock_version + 1'),
            ]);
            abort_unless($updated === 1, 409, 'This contribution changed after you opened it. Refresh and try again.');
            foreach (array_unique(array_filter([$oldGoalId, $goal?->id])) as $goalId) {
                $ownedGoal = $user->savingsGoals()->whereKey($goalId)->first();
                if ($ownedGoal) {
                    $this->syncGoal($ownedGoal);
                }
            }
        });
        return back()->with('success', 'Savings contribution updated.');
    }

    public function destroy(Request $request, int $saving): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $user = $request->user();
        $record = $user->savings()->whereKey($saving)->firstOrFail();
        DB::transaction(function () use ($user, $record, $data): void {
            $deleted = $user->savings()->whereKey($record->id)->where('lock_version', (int) $data['lock_version'])->delete();
            abort_unless($deleted === 1, 409, 'This contribution changed after you opened it. Refresh before deleting it.');
            if ($record->goal_id) {
                $goal = $user->savingsGoals()->whereKey($record->goal_id)->first();
                if ($goal) {
                    $this->syncGoal($goal);
                }
            }
        });
        return back()->with('success', 'Savings contribution deleted.');
    }

    /** Derive the denormalized balance from the ledger after each contribution mutation. */
    private function syncGoal(SavingsGoal $goal): void
    {
        // Serialize balance reconciliation for this goal when concurrent contributions arrive.
        $lockedGoal = SavingsGoal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();
        $total = $lockedGoal->contributions()->sum('amount');
        $completed = $lockedGoal->target_amount !== null
            && round((float) $total, 2) >= round((float) $lockedGoal->target_amount, 2);

        $lockedGoal->forceFill([
            'current_amount' => $total,
            'status' => $completed ? 'completed' : 'active',
        ])->save();
    }
}
