@extends('layouts.app')
@section('title','Dashboard')
@section('heading','Your overview')
@section('content')
<div class="eyebrow" style="margin-bottom:14px">{{ $monthLabel }} · Monthly summary</div>
<div class="grid stats">
<section class="card"><div class="stat-label">Total income</div><div class="stat-value">KES {{ number_format($totalIncome,2) }}</div><div class="stat-note">Money received this month</div></section>
<section class="card"><div class="stat-label">Total expenses</div><div class="stat-value">KES {{ number_format($totalExpenses,2) }}</div><div class="stat-note">Money spent this month</div></section>
<section class="card"><div class="stat-label">Savings</div><div class="stat-value">KES {{ number_format($totalSavings,2) }}</div><div class="stat-note">Savings contributions</div></section>
<section class="card"><div class="stat-label">Available balance</div><div class="stat-value" style="color:{{ $netBalance>=0?'#16835b':'#c24141' }}">KES {{ number_format($netBalance,2) }}</div><div class="stat-note">Income less expenses and savings</div></section></div>
<div class="grid two-col"><section class="card"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:17px"><h2 class="section-heading" style="margin:0">Recent income</h2><a class="btn btn-light btn-small" href="{{ route('income.index') }}">View all</a></div>
@forelse($recentIncome as $item)<div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f2f6"><div><strong style="font-size:12px">{{ $item->source }}</strong><div class="muted" style="font-size:11px;margin-top:4px">{{ $item->transaction_date->format('d M Y') }}</div></div><strong style="font-size:12px;color:#16835b">+ KES {{ number_format((float)$item->amount,2) }}</strong></div>@empty<p class="muted" style="font-size:12px">No income recorded yet.</p>@endforelse</section>
<section class="card"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:17px"><h2 class="section-heading" style="margin:0">Recent expenses</h2><a class="btn btn-light btn-small" href="{{ route('expenses.index') }}">View all</a></div>
@forelse($recentExpenses as $item)<div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f2f6"><div><strong style="font-size:12px">{{ $item->category }}</strong><div class="muted" style="font-size:11px;margin-top:4px">{{ $item->transaction_date->format('d M Y') }}</div></div><strong style="font-size:12px;color:#c24141">− KES {{ number_format((float)$item->amount,2) }}</strong></div>@empty<p class="muted" style="font-size:12px">No expenses recorded yet.</p>@endforelse</section></div>
@endsection
