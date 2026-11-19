<?php

namespace App\Http\Controllers;

use App\Services\FinancialAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class FinancialAssistantController extends Controller
{
    public function index(Request $request, FinancialAssistantService $assistant): View
    {
        return view('finance.assistant.index', [
            'tips' => $assistant->personalisedTips($request->user()),
            'providerConfigured' => is_string(config('financial_assistant.api_key'))
                && trim((string) config('financial_assistant.api_key')) !== '',
            'provider' => config('financial_assistant.provider'),
        ]);
    }

    public function ask(Request $request, FinancialAssistantService $assistant): RedirectResponse
    {
        $key = 'financial-assistant:'.$request->user()->getKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'question' => 'You have reached the assistant limit. Please wait before asking another question.',
            ]);
        }

        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1500'],
            'share_financial_context' => ['nullable', 'accepted'],
        ]);

        RateLimiter::hit($key, 60);

        $result = $assistant->answer(
            $request->user(),
            trim($data['question']),
            isset($data['share_financial_context']),
        );

        return back()
            ->with('assistant_answer', $result['answer'])
            ->with('assistant_question', trim($data['question']))
            ->with('assistant_provider_available', $result['provider_available']);
    }
}
