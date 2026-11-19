@extends('layouts.app')

@section('title', 'Financial Assistant')
@section('heading', 'Financial Assistant')

@section('content')
<div class="grid" style="grid-template-columns:minmax(0,1.35fr) minmax(280px,.85fr);align-items:start">
    <section class="card">
        <div style="display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:12px">
            <div>
                <h2 class="section-heading" style="margin-bottom:5px">Ask a money question</h2>
                <p class="muted" style="font-size:12px;margin:0">Budgeting, saving habits and financial literacy.</p>
            </div>
            <span style="font-size:10px;font-weight:700;color:#64748b;background:#f1f4f9;border-radius:20px;padding:6px 9px">{{ strtoupper($provider) }}</span>
        </div>

        @if(session('assistant_answer'))
            <div style="border:1px solid #e4eaf3;background:#f8faff;border-radius:11px;padding:16px;margin:16px 0">
                <div class="eyebrow" style="margin-bottom:7px">Your question</div>
                <p style="font-size:13px;color:#475569;margin:0 0 14px">{{ session('assistant_question') }}</p>
                <div class="eyebrow" style="margin-bottom:7px">Assistant response</div>
                <div style="font-size:13px;line-height:1.75;color:#26344d;white-space:pre-wrap">{{ session('assistant_answer') }}</div>
                @if(!session('assistant_provider_available'))
                    <p class="muted" style="font-size:11px;margin:12px 0 0">Your financial records were not changed.</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('assistant.ask') }}">
            @csrf
            <div class="field">
                <label for="question">Your question</label>
                <textarea id="question" name="question" required minlength="3" maxlength="1500" rows="5" placeholder="For example: How can I plan a realistic monthly budget on irregular income?" style="width:100%;border:1px solid #dfe5ee;border-radius:8px;padding:12px;font:13px 'DM Sans',sans-serif;resize:vertical">{{ old('question') }}</textarea>
                <div class="muted" style="font-size:10px;margin-top:5px">Do not include passwords, account numbers or other sensitive identifiers.</div>
            </div>
            <label style="display:flex;gap:9px;align-items:flex-start;margin:15px 0;color:#52617a;font-size:12px;line-height:1.55">
                <input type="checkbox" name="share_financial_context" value="1" style="margin-top:3px">
                <span>I explicitly consent to send my current-month financial totals and ratios (income, expenses, savings and budget) to the configured AI provider for this answer. No transaction rows, names or account identifiers are sent.</span>
            </label>
            <button class="btn" type="submit" @if(!$providerConfigured) disabled style="opacity:.55;cursor:not-allowed" @endif>Send question</button>
            @if(!$providerConfigured)
                <p class="muted" style="font-size:11px;margin:10px 0 0">Chat is unavailable until a server administrator configures the provider. Personalised tips below continue to work locally.</p>
            @endif
        </form>
    </section>

    <aside class="card">
        <h2 class="section-heading">Your monthly snapshot</h2>
        <p class="muted" style="font-size:11px;margin:-8px 0 15px">Calculated locally from your own records. Not sent to an AI provider unless you tick the consent box above.</p>
        <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
            <div style="padding:12px;background:#f7f9fc;border-radius:9px"><div class="stat-label">Income</div><div style="font:600 17px 'Space Grotesk',sans-serif;margin-top:6px">KES {{ number_format($tips['income'], 2) }}</div></div>
            <div style="padding:12px;background:#f7f9fc;border-radius:9px"><div class="stat-label">Expenses</div><div style="font:600 17px 'Space Grotesk',sans-serif;margin-top:6px">KES {{ number_format($tips['expenses'], 2) }}</div></div>
            <div style="padding:12px;background:#f7f9fc;border-radius:9px"><div class="stat-label">Savings</div><div style="font:600 17px 'Space Grotesk',sans-serif;margin-top:6px">KES {{ number_format($tips['savings'], 2) }}</div></div>
            <div style="padding:12px;background:#f7f9fc;border-radius:9px"><div class="stat-label">Budget usage</div><div style="font:600 17px 'Space Grotesk',sans-serif;margin-top:6px">{{ number_format($tips['budget_usage'], 1) }}%</div></div>
        </div>
        <h3 class="section-heading" style="margin:22px 0 10px">Personalised tips</h3>
        @foreach($tips['tips'] as $tip)
            <div style="display:flex;gap:10px;padding:11px 0;border-top:1px solid #edf0f5">
                <span style="color:#526b9b;font-weight:700">•</span>
                <p style="font-size:12px;line-height:1.6;color:#52617a;margin:0">{{ $tip }}</p>
            </div>
        @endforeach
    </aside>
</div>
<div class="muted" style="font-size:11px;line-height:1.6;margin-top:16px">Important: AI-generated responses may be inaccurate and are for general financial education only. They are not professional financial, investment, tax or legal advice. Verify important information before acting.</div>
@endsection
