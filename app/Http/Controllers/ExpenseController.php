<?php

namespace App\Http\Controllers;

use App\Services\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request, CurrencyRateService $currencies): View
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

        return view('finance.expenses.index', [
            'records' => $records,
            'month' => $month,
            'total' => $total,
            'submissionToken' => (string) Str::uuid(),
            'currencies' => $currencies->currencies(),
            'wallets' => $request->user()->wallets()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CurrencyRateService $currencies): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'offline_owner_id' => ['sometimes', 'integer'],
            'wallet_id' => ['nullable', 'integer'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3', 'in:'.implode(',', $currencies->currencies())],
            'accept_stale_rate' => ['sometimes', 'boolean'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        if (isset($data['offline_owner_id']) && (string) $data['offline_owner_id'] !== (string) $request->user()->getAuthIdentifier()) {
            return response()->json(['message' => 'The signed-in account changed. Sign in to the original account before syncing this pending transaction.'], 409);
        }

        unset($data['offline_owner_id']);
        $token = $data['submission_token'];
        $existing = $request->user()->expenses()->where('submission_token', $token)->first();
        if ($existing) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Expense record was already saved.',
                    'record_id' => $existing->getKey(),
                ], 200);
            }

            return back()->with('success', 'Expense record was already saved.');
        }

        $wallet = isset($data['wallet_id'])
            ? $request->user()->wallets()->whereKey($data['wallet_id'])->first()
            : $request->user()->wallets()->where('is_default', true)->first();
        abort_unless($wallet, 404);
        $originalAmount = (float) $data['amount'];
        $originalCurrency = $data['currency'] ?? 'KES';
        unset($data['submission_token'], $data['wallet_id'], $data['currency'], $data['accept_stale_rate']);

        $quote = $currencies->quote($originalCurrency, 'KES');
        if ($quote === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'An exchange rate is not available. Choose KES or try again later.'], 422);
            }

            return back()->withErrors(['currency' => 'An exchange rate is not available. Choose KES or try again later.'])->withInput();
        }
        if ($quote['stale'] && ! $request->boolean('accept_stale_rate')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The provider is unavailable. Review and explicitly accept the cached rate before saving.'], 422);
            }

            return back()->withErrors(['currency' => 'The provider is unavailable. Review and explicitly accept the cached rate before saving.'])
                ->with('stale_expense_quote', $quote)
                ->withInput();
        }

        $convertedAmount = round($originalAmount * $quote['rate'], 2);
        if ($convertedAmount > 999999999999.99) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The converted KES amount exceeds the maximum supported expense value.'], 422);
            }

            return back()->withErrors(['amount' => 'The converted KES amount exceeds the maximum supported expense value.'])->withInput();
        }
        $data['amount'] = $convertedAmount;
        $data['original_amount'] = $originalAmount;
        $data['original_currency'] = $originalCurrency;
        $data['exchange_rate'] = $quote['rate'];
        $data['converted_amount'] = $convertedAmount;
        $data['exchange_rate_updated_at'] = $quote['updated_at'];
        $data['exchange_rate_source'] = $quote['source'];

        $record = $request->user()->expenses()->firstOrCreate(['submission_token' => $token], [...$data, 'wallet_id' => $wallet->id]);

        $message = $quote['stale']
            ? 'Expense record saved using a cached exchange rate. Review the stored rate metadata.'
            : 'Expense record saved.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'record_id' => $record->getKey()], 201);
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, int $expense): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->expenses()->whereKey($expense)->firstOrFail();

        $expectedVersion = (int) $data['lock_version'];
        unset($data['lock_version']);
        $data['original_amount'] = $data['amount'];
        $data['original_currency'] = 'KES';
        $data['exchange_rate'] = 1;
        $data['converted_amount'] = $data['amount'];
        $data['exchange_rate_updated_at'] = now();
        $data['exchange_rate_source'] = 'Same currency';

        $updated = $request->user()->expenses()
            ->whereKey($expense)
            ->where('lock_version', $expectedVersion)
            ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);

        abort_unless($updated === 1, 409, 'This expense record changed after you opened it. Refresh the page and apply your changes again.');

        return back()->with('success', 'Expense record updated.');
    }

    public function destroy(Request $request, int $expense): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $request->user()->expenses()->whereKey($expense)->firstOrFail();

        $deleted = $request->user()->expenses()
            ->whereKey($expense)
            ->where('lock_version', (int) $data['lock_version'])
            ->delete();

        abort_unless($deleted === 1, 409, 'This expense record changed after you opened it. Refresh the page before deleting it.');

        return back()->with('success', 'Expense record deleted.');
    }
}
