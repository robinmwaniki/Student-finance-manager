<?php

namespace App\Http\Controllers;

use App\Services\CurrencyRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Rule;
use Illuminate\Validation\ValidationException;
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

        return view('finance.expenses.index', [
            'records' => $records,
            'month' => $month,
            'total' => $total,
            'submissionToken' => (string) Str::uuid(),
            'currencies' => CurrencyRateService::CURRENCIES,
        ]);
    }

    public function store(Request $request, CurrencyRateService $rates): RedirectResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyRateService::CURRENCIES)],
            'manual_exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'manual_rate_confirmation' => ['sometimes', 'accepted'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $token = $data['submission_token'];
        unset($data['submission_token']);
        $currencyFields = $this->currencyFields($data, $rates);
        unset($data['currency'], $data['manual_exchange_rate'], $data['manual_rate_confirmation']);

        $request->user()->expenses()->firstOrCreate(
            ['submission_token' => $token],
            [...$data, ...$currencyFields],
        );

        return back()->with('success', 'Expense record saved. Budgets and reports use the converted KES amount.');
    }

    public function update(Request $request, int $expense, CurrencyRateService $rates): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyRateService::CURRENCIES)],
            'manual_exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'manual_rate_confirmation' => ['sometimes', 'accepted'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->expenses()->whereKey($expense)->firstOrFail();
        $expectedVersion = (int) $data['lock_version'];
        unset($data['lock_version']);
        $currencyFields = $this->currencyFields($data, $rates);
        unset($data['currency'], $data['manual_exchange_rate'], $data['manual_rate_confirmation']);

        $updated = $request->user()->expenses()
            ->whereKey($expense)
            ->where('lock_version', $expectedVersion)
            ->update([...$data, ...$currencyFields, 'lock_version' => DB::raw('lock_version + 1')]);

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

    /**
     * Store the converted KES ledger amount alongside original currency and rate provenance.
     *
     * @return array{amount: string, original_amount: string, original_currency: string, exchange_rate: string, exchange_rate_source: string, exchange_rate_at: string}
     */
    private function currencyFields(array $data, CurrencyRateService $rates): array
    {
        $currency = strtoupper((string) ($data['currency'] ?? 'KES'));
        $originalAmount = (float) $data['amount'];

        if ($currency === 'KES') {
            return [
                'amount' => number_format($originalAmount, 2, '.', ''),
                'original_amount' => number_format($originalAmount, 2, '.', ''),
                'original_currency' => 'KES',
                'exchange_rate' => '1.00000000',
                'exchange_rate_source' => 'Identity conversion',
                'exchange_rate_at' => now('UTC')->toDateTimeString(),
            ];
        }

        $quote = $rates->convert($originalAmount, $currency, 'KES');
        if ($quote !== null) {
            return [
                'amount' => $quote['amount'],
                'original_amount' => number_format($originalAmount, 2, '.', ''),
                'original_currency' => $currency,
                'exchange_rate' => number_format($quote['rate'], 8, '.', ''),
                'exchange_rate_source' => $quote['source'].($quote['stale'] ? ' (stale cached rate)' : ''),
                'exchange_rate_at' => Carbon\CarbonImmutable::parse($quote['as_of'])->utc()->toDateTimeString(),
            ];
        }

        $manualRate = $data['manual_exchange_rate'] ?? null;
        if ($manualRate !== null && isset($data['manual_rate_confirmation'])) {
            $convertedAmount = $rates->convertWithRate($originalAmount, (float) $manualRate);
            if ($convertedAmount !== null) {
                return [
                    'amount' => $convertedAmount,
                    'original_amount' => number_format($originalAmount, 2, '.', ''),
                    'original_currency' => $currency,
                    'exchange_rate' => number_format((float) $manualRate, 8, '.', ''),
                    'exchange_rate_source' => 'User-entered manual rate',
                    'exchange_rate_at' => now('UTC')->toDateTimeString(),
                ];
            }
        }

        throw ValidationException::withMessages([
            'currency' => 'No current or recent exchange rate is available. Retry later or enter a manually verified rate and confirm it.',
        ]);
    }
}use App\Services\CurrencyRateService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        return view('finance.expenses.index', [
            'records' => $records,
            'month' => $month,
            'total' => $total,
            'submissionToken' => (string) Str::uuid(),
            'currencies' => CurrencyRateService::CURRENCIES,
        ]);
    }

    public function store(Request $request, CurrencyRateService $rates): RedirectResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyRateService::CURRENCIES)],
            'manual_exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'manual_rate_confirmation' => ['sometimes', 'accepted'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $token = $data['submission_token'];
        unset($data['submission_token']);
        $currencyFields = $this->currencyFields($data, $rates);
        unset($data['currency'], $data['manual_exchange_rate'], $data['manual_rate_confirmation']);

        $request->user()->expenses()->firstOrCreate(
            ['submission_token' => $token],
            [...$data, ...$currencyFields],
        );

        return back()->with('success', 'Expense record saved. Budgets and reports use the converted KES amount.');
    }

    public function update(Request $request, int $expense, CurrencyRateService $rates): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyRateService::CURRENCIES)],
            'manual_exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'manual_rate_confirmation' => ['sometimes', 'accepted'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->expenses()->whereKey($expense)->firstOrFail();
        $expectedVersion = (int) $data['lock_version'];
        unset($data['lock_version']);
        $currencyFields = $this->currencyFields($data, $rates);
        unset($data['currency'], $data['manual_exchange_rate'], $data['manual_rate_confirmation']);

        $updated = $request->user()->expenses()
            ->whereKey($expense)
            ->where('lock_version', $expectedVersion)
            ->update([...$data, ...$currencyFields, 'lock_version' => DB::raw('lock_version + 1')]);

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

    /**
     * Store the converted KES ledger amount alongside original currency and rate provenance.
     *
     * @return array{amount: string, original_amount: string, original_currency: string, exchange_rate: string, exchange_rate_source: string, exchange_rate_at: string}
     */
    private function currencyFields(array $data, CurrencyRateService $rates): array
    {
        $currency = strtoupper((string) ($data['currency'] ?? 'KES'));
        $originalAmount = (float) $data['amount'];

        if ($currency === 'KES') {
            return [
                'amount' => number_format($originalAmount, 2, '.', ''),
                'original_amount' => number_format($originalAmount, 2, '.', ''),
                'original_currency' => 'KES',
                'exchange_rate' => '1.00000000',
                'exchange_rate_source' => 'Identity conversion',
                'exchange_rate_at' => now('UTC')->toDateTimeString(),
            ];
        }

        $quote = $rates->convert($originalAmount, $currency, 'KES');
        if ($quote !== null) {
            return [
                'amount' => $quote['amount'],
                'original_amount' => number_format($originalAmount, 2, '.', ''),
                'original_currency' => $currency,
                'exchange_rate' => number_format($quote['rate'], 8, '.', ''),
                'exchange_rate_source' => $quote['source'].($quote['stale'] ? ' (stale cached rate)' : ''),
                'exchange_rate_at' => \Carbon\CarbonImmutable::parse($quote['as_of'])->utc()->toDateTimeString(),
            ];
        }

        $manualRate = $data['manual_exchange_rate'] ?? null;
        if ($manualRate !== null && isset($data['manual_rate_confirmation'])) {
            $convertedAmount = $rates->convertWithRate($originalAmount, (float) $manualRate);
            if ($convertedAmount !== null) {
                return [
                    'amount' => $convertedAmount,
                    'original_amount' => number_format($originalAmount, 2, '.', ''),
                    'original_currency' => $currency,
                    'exchange_rate' => number_format((float) $manualRate, 8, '.', ''),
                    'exchange_rate_source' => 'User-entered manual rate',
                    'exchange_rate_at' => now('UTC')->toDateTimeString(),
                ];
            }
        }

        throw ValidationException::withMessages([
            'currency' => 'No current or recent exchange rate is available. Retry later or enter a manually verified rate and confirm it.',
        ]);
    }
}
