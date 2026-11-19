<?php

namespace App\Http\Controllers;

use App\Services\CurrencyRateService;
use App\Services\FinancialCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialToolsController extends Controller
{
    public function index(): View
    {
        return view('finance.tools.index', [
            'currencies' => CurrencyRateService::CURRENCIES,
        ]);
    }

    public function loan(Request $request, FinancialCalculatorService $calculator): RedirectResponse
    {
        $data = $request->validate([
            'principal' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'annual_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'term_months' => ['required', 'integer', 'min:1', 'max:360'],
            'frequency' => ['required', 'in:weekly,biweekly,monthly,quarterly'],
        ]);

        $result = $calculator->loanSchedule(
            (float) $data['principal'],
            (float) $data['annual_rate'],
            (int) $data['term_months'],
            $data['frequency'],
        );

        return back()->withInput()->with('loan_result', $result);
    }

    public function split(Request $request, FinancialCalculatorService $calculator): RedirectResponse
    {
        $data = $request->validate([
            'subtotal' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'people' => ['required', 'integer', 'min:2', 'max:100'],
            'tip_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $result = $calculator->splitBill(
            (float) $data['subtotal'],
            (int) $data['people'],
            (float) $data['tip_percent'],
        );

        return back()->withInput()->with('split_result', $result);
    }

    public function convert(Request $request, CurrencyRateService $rates): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'from' => ['required', 'string', 'size:3', 'in:'.implode(',', CurrencyRateService::CURRENCIES)],
            'to' => ['required', 'string', 'size:3', 'in:'.implode(',', CurrencyRateService::CURRENCIES)],
        ]);

        $result = $rates->convert((float) $data['amount'], $data['from'], $data['to']);

        if ($result === null) {
            return response()->json([
                'message' => 'No current or recent exchange rate is available. Try again later or use a manually verified rate.',
            ], 503);
        }

        return response()->json([
            'amount' => $result['amount'],
            'from' => strtoupper($data['from']),
            'to' => strtoupper($data['to']),
            'rate' => $result['rate'],
            'source' => $result['source'],
            'as_of' => $result['as_of'],
            'stale' => $result['stale'],
        ]);
    }
}
