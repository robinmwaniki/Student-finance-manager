<?php

namespace App\Http\Controllers;

use App\Services\BillSplitService;
use App\Services\CurrencyRateService;
use App\Services\LoanCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialToolsController extends Controller
{
    public function index(CurrencyRateService $currencies): View
    {
        return view('finance.tools.index', ['currencies' => $currencies->currencies()]);
    }

    public function loan(Request $request, LoanCalculatorService $calculator): RedirectResponse
    {
        $data = $request->validate([
            'principal' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'annual_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'term_months' => ['required', 'integer', 'min:1', 'max:360'],
            'frequency' => ['required', 'in:monthly,weekly,biweekly'],
        ]);

        $result = $calculator->calculate((float) $data['principal'], (float) $data['annual_rate'], (int) $data['term_months'], $data['frequency']);

        return back()->with('loan_result', $result)->withInput();
    }

    public function split(Request $request, BillSplitService $splitter): RedirectResponse
    {
        $data = $request->validate([
            'subtotal' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'participants' => ['required', 'integer', 'min:1', 'max:100'],
            'tip_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $result = $splitter->split((float) $data['subtotal'], (int) $data['participants'], (float) $data['tip_percent']);

        return back()->with('split_result', $result)->withInput();
    }

    public function currency(Request $request, CurrencyRateService $currencies): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'from' => ['required', 'string', 'size:3', 'in:'.implode(',', $currencies->currencies())],
            'to' => ['required', 'string', 'size:3', 'in:'.implode(',', $currencies->currencies())],
            'accept_stale_rate' => ['sometimes', 'boolean'],
        ]);
        $quote = $currencies->quote($data['from'], $data['to']);

        if ($quote === null) {
            return back()->withErrors(['currency' => 'An exchange rate is not available. Try again later or use a different currency pair.'])->withInput();
        }

        if ($quote['stale'] && ! $request->boolean('accept_stale_rate')) {
            return back()->withErrors(['currency' => 'The provider is unavailable. Review the cached rate and explicitly accept it before converting.'])
                ->with('stale_quote', $quote)->withInput();
        }

        return back()->with('currency_result', [
            ...$quote,
            'amount' => (float) $data['amount'],
            'converted' => round((float) $data['amount'] * $quote['rate'], 2),
        ])->withInput();
    }

    public function rate(Request $request, CurrencyRateService $currencies): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'size:3', 'in:'.implode(',', $currencies->currencies())],
            'to' => ['required', 'string', 'size:3', 'in:'.implode(',', $currencies->currencies())],
        ]);
        $quote = $currencies->quote($data['from'], $data['to']);

        return response()->json($quote ?? ['message' => 'Rate unavailable.'], $quote === null ? 503 : 200);
    }
}
