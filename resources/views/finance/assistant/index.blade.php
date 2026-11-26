@extends('layouts.app')
@section('title', 'Financial insights')
@section('heading', 'Financial insights')
@section('content')
<section class="grid stats" style="margin-bottom:18px">
    <article class="card"><div class="stat-label">Income · {{ $insights['period'] }}</div><div class="stat-value">KES {{ number_format($insights['income'], 2) }}</div></article>
    <article class="card"><div class="stat-label">Expenses · {{ $insights['period'] }}</div><div class="stat-value">KES {{ number_format($insights['expenses'], 2) }}</div></article>
    <article class="card"><div class="stat-label">Savings · {{ $insights['period'] }}</div><div class="stat-value">KES {{ number_format($insights['savings'], 2) }}</div></article>
    <article class="card"><div class="stat-label">Available after recorded activity</div><div class="stat-value" style="color:{{ $insights['net'] < 0 ? '#b42318' : '#16835b' }}">KES {{ number_format($insights['net'], 2) }}</div></article>
</section>

<section class="card" style="margin-bottom:18px">
    <div class="eyebrow">Free · Calculated inside your account</div>
    <h2 class="section-heading" style="margin-top:6px">Spending breakdown</h2>
    @if(count($insights['top_categories']))
        <div class="table-wrap"><table><thead><tr><th>Category</th><th>Spent</th><th>Share of expenses</th></tr></thead><tbody>
        @foreach($insights['top_categories'] as $category)
            <tr><td>{{ $category['category'] }}</td><td class="amount">KES {{ number_format($category['amount'], 2) }}</td><td>{{ number_format($category['share'], 1) }}%</td></tr>
        @endforeach
        </tbody></table></div>
    @else
        <p class="muted" style="font-size:12px">No expenses have been recorded this month yet. Import a statement or add expenses to see your spending breakdown.</p>
    @endif
</section>

<section class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Personal finance recommendations</h2>
    <p class="muted" style="font-size:11px;line-height:1.7;margin-top:-8px">These suggestions use your recorded income, expenses, savings and budgets. They are calculated locally without an AI provider. They never change your budgets or move money.</p>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px">
        @foreach($insights['recommendations'] as $recommendation)
            <article style="border:1px solid #e8edf4;border-radius:11px;padding:14px">
                <div style="font-size:10px;font-weight:700;color:{{ $recommendation['level'] === 'warning' ? '#b45309' : ($recommendation['level'] === 'success' ? '#16835b' : '#43516a') }};margin-bottom:7px">{{ strtoupper($recommendation['level']) }}</div>
                <strong style="font-size:13px">{{ $recommendation['title'] }}</strong>
                <p class="muted" style="font-size:11px;line-height:1.7;margin:8px 0 0">{{ $recommendation['detail'] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Additional personalized tips</h2>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px">
        @foreach($tips as $tip)
            <article style="border:1px solid #e8edf4;border-radius:11px;padding:14px">
                <strong style="font-size:13px">{{ $tip['title'] }}</strong>
                <p class="muted" style="font-size:11px;line-height:1.7;margin:8px 0 0">{{ $tip['detail'] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
        <div><div class="eyebrow">Optional AI provider</div><h2 class="section-heading" style="margin:6px 0 0">Ask a money question</h2></div>
        <span style="font-size:11px;color:{{ $providerAvailable ? '#16835b' : '#8490a3' }};font-weight:700">{{ $providerAvailable ? $providerLabel.' connected' : 'AI provider not configured' }}</span>
    </div>
    <p class="muted" style="font-size:12px;line-height:1.7;margin:12px 0 0">The local recommendations above work without a paid service. The optional AI chat may require a provider account and is not needed for this feature.</p>
    @if($providerAvailable)
        <form method="POST" action="{{ route('assistant.ask') }}" style="margin-top:16px">
            @csrf
            <div class="field"><label for="question">What would you like help understanding?</label><textarea id="question" name="question" maxlength="1000" required rows="3" style="width:100%;border:1px solid #dfe5ee;border-radius:8px;padding:12px;font:13px 'DM Sans',sans-serif;resize:vertical" placeholder="For example: How can I plan an emergency fund?">{{ old('question') }}</textarea></div>
            <label style="display:flex;align-items:flex-start;gap:9px;margin:13px 0;color:#52617a;font-size:11px;line-height:1.6"><input type="checkbox" name="include_financial_summary" value="1" style="margin-top:2px"><span>Share only aggregate 30-day financial totals with the configured AI provider for this question.</span></label>
            <button class="btn" type="submit">Ask assistant</button>
        </form>
    @else
        <p class="muted" style="font-size:11px;margin-top:12px">No AI provider is configured. You can use all local insights without one.</p>
    @endif
</section>
@if(session('assistant_answer'))
<section class="card"><div class="eyebrow">Assistant response</div><p style="font-size:13px;line-height:1.8;white-space:pre-wrap;margin:10px 0 0">{{ session('assistant_answer') }}</p></section>
@endif
@endsection
