<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->query('month', now(config('app.timezone'))->format('Y-m'));
        abort_unless($this->validMonth($month), 404);
        $category = $request->query('category');
        abort_if($category !== null && (! is_string($category) || mb_strlen($category) > 50), 404);
        $editId = $request->query('edit');
        abort_if($editId !== null && ! ctype_digit((string) $editId), 404);

        $user = $request->user();
        $query = $user->budgets()->where('budget_month', $month);
        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        $budgets = $query->orderBy('category')->paginate(15)->withQueryString();
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $spent = $user->expenses()
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');

        $budgets->getCollection()->transform(function ($budget) use ($spent): object {
            $budgetCents = $this->toCents((string) $budget->amount);
            $spentAmount = (string) ($spent[$budget->category] ?? '0.00');
            $spentCents = $this->toCents($spentAmount);

            $budget->spent = $spentAmount;
            $budget->remaining = ($budgetCents - $spentCents) / 100;
            $budget->utilization = $budgetCents > 0
                ? ($spentCents / $budgetCents) * 100
                : 0;

            return $budget;
        });

        $allBudgets = $user->budgets()->where('budget_month', $month)->get(['category', 'amount']);
        $budgetTotalCents = $allBudgets->sum(fn ($item): int => $this->toCents((string) $item->amount));
        $spentTotalCents = $allBudgets->sum(fn ($item): int => $this->toCents((string) ($spent[$item->category] ?? '0.00')));
        $budgetTotal = $budgetTotalCents / 100;
        $spentTotal = $spentTotalCents / 100;
        $categories = $user->budgets()->where('budget_month', $month)->orderBy('category')->pluck('category');

        return view('finance.budgets.index', [
            'budgets' => $budgets,
            'month' => $month,
            'category' => $category ?? '',
            'categories' => $categories,
            'budgetTotal' => $budgetTotal,
            'spentTotal' => $spentTotal,
            'remainingTotal' => $budgetTotal - $spentTotal,
            'submissionToken' => (string) Str::uuid(),
            'editBudget' => $editId !== null
                ? $user->budgets()->where('budget_month', $month)->whereKey((int) $editId)->firstOrFail()
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedBudget($request);
        $token = $data['submission_token'];
        unset($data['submission_token']);
        $data['budget_month'] = CarbonImmutable::parse($data['date'])->format('Y-m');

        try {
            $request->user()->budgets()->firstOrCreate(['submission_token' => $token], $data);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            return back()->withErrors(['category' => 'A budget already exists for this category and month.'])->withInput();
        }

        return redirect()->route('budgets.index', ['month' => $data['budget_month']])->with('success', 'Monthly budget saved.');
    }

    public function update(Request $request, int $budget): RedirectResponse
    {
        $data = $this->validatedBudget($request, $budget);
        $version = (int) $data['lock_version'];
        unset($data['lock_version'], $data['submission_token']);
        $data['budget_month'] = CarbonImmutable::parse($data['date'])->format('Y-m');

        $user = $request->user();
        $user->budgets()->whereKey($budget)->firstOrFail();

        try {
            $updated = $user->budgets()->whereKey($budget)->where('lock_version', $version)
                ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            return back()->withErrors(['category' => 'Another budget already exists for this category and month.'])->withInput();
        }

        abort_unless($updated === 1, 409, 'This budget changed after you opened it. Refresh and try again.');

        return redirect()->route('budgets.index', ['month' => $data['budget_month']])->with('success', 'Monthly budget updated.');
    }

    public function destroy(Request $request, int $budget): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $user = $request->user();
        $record = $user->budgets()->whereKey($budget)->firstOrFail();
        $deleted = $user->budgets()->whereKey($budget)->where('lock_version', (int) $data['lock_version'])->delete();

        abort_unless($deleted === 1, 409, 'This budget changed after you opened it. Refresh before deleting it.');

        return redirect()->route('budgets.index', ['month' => $record->budget_month])->with('success', 'Budget deleted.');
    }

    public function copy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'to_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', 'different:from_month'],
            'strategy' => ['required', Rule::in(['skip', 'report', 'replace'])],
            'confirm_replace' => ['exclude_unless:strategy,replace', 'required', 'accepted'],
        ]);
        abort_unless($data['to_month'] > $data['from_month'], 422, 'Budgets can only be copied to a later month.');

        $user = $request->user();
        $source = $user->budgets()->where('budget_month', $data['from_month'])->orderBy('category')->get();
        if ($source->isEmpty()) {
            return back()->withErrors(['from_month' => 'There are no budgets in the selected source month.']);
        }

        $target = $user->budgets()->where('budget_month', $data['to_month'])->get()->keyBy('category');
        $conflicts = $source->pluck('category')->filter(fn ($name): bool => $target->has($name))->values();

        if ($data['strategy'] === 'report' && $conflicts->isNotEmpty()) {
            return redirect()->route('budgets.index', ['month' => $data['to_month']])
                ->withErrors(['copy' => 'No budgets were copied. Existing categories: '.$conflicts->implode(', ').'.']);
        }

        try {
            $copied = DB::transaction(function () use ($user, $source, $target, $data): int {
                if ($data['strategy'] === 'replace') {
                    $user->budgets()->where('budget_month', $data['to_month'])->delete();
                }

                $count = 0;
                foreach ($source as $budget) {
                    if ($data['strategy'] === 'skip' && $target->has($budget->category)) {
                        continue;
                    }

                    $user->budgets()->create([
                        'category' => $budget->category,
                        'amount' => $budget->amount,
                        'limit_amount' => $budget->limit_amount,
                        'date' => $data['to_month'].'-01',
                        'budget_month' => $data['to_month'],
                    ]);
                    $count++;
                }

                return $count;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            return redirect()->route('budgets.index', ['month' => $data['to_month']])
                ->withErrors(['copy' => 'The target month changed while copying. Refresh the budgets and retry.']);
        }

        $message = $data['strategy'] === 'replace'
            ? "Replaced the target month's budgets with {$copied} copied categories."
            : "Copied {$copied} budget categories.".($conflicts->isNotEmpty() ? ' Existing categories were skipped: '.$conflicts->implode(', ').'.' : '');

        return redirect()->route('budgets.index', ['month' => $data['to_month']])->with('success', $message);
    }

    private function validatedBudget(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'submission_token' => [$ignoreId === null ? 'required' : 'nullable', 'uuid'],
            'lock_version' => [$ignoreId === null ? 'nullable' : 'required', 'integer', 'min:0'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'date' => ['required', 'date'],
        ]);
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function validMonth(mixed $month): bool
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            || str_contains($exception->getMessage(), 'budgets_user_category_month_unique')
            || str_contains($exception->getMessage(), 'budgets_user_submission_token_unique');
    }
}
