<?php

namespace App\Http\Controllers;

use App\Services\FinancialAssistantService;
use App\Services\FinancialInsightsService;
use App\Services\FinancialTipsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class FinancialAssistantController extends Controller
{
    public function index(Request $request, FinancialTipsService $tips, FinancialAssistantService $assistant, FinancialInsightsService $insights): View
    {
        return view('finance.assistant.index', [
            'tips' => $tips->forUser($request->user()),
            'insights' => $insights->forUser($request->user()),
            'providerAvailable' => $assistant->isConfigured(),
            'providerLabel' => $assistant->providerLabel(),
        ]);
    }

    public function ask(Request $request, FinancialAssistantService $assistant): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:8', 'max:1000'],
            'include_financial_summary' => ['sometimes', 'boolean'],
        ]);

        $rateLimitKey = 'financial-assistant:'.$request->user()->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return back()->withErrors([
                'assistant' => 'You have reached the assistant limit. Please wait before asking another question.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);

        $result = $assistant->ask(
            $request->user(),
            $data['question'],
            $request->boolean('include_financial_summary'),
        );

        if (! $result['success']) {
            return back()->withErrors(['assistant' => $result['message']]);
        }

        return redirect()->route('assistant.index')->with('assistant_answer', $result['answer']);
    }
}
