@extends('layouts.app')
@section('title','Financial reports')
@section('heading','Financial reports')
@section('content')
<section class="card" style="margin-bottom:18px"><h2 class="section-heading">Build a report</h2>
<form method="GET" action="{{ route('reports.index') }}" class="form-grid">
<div class="field"><label for="start_date">From</label><input id="start_date" name="start_date" type="date" value="{{ $filters['start']->toDateString() }}" required></div>
<div class="field"><label for="end_date">To</label><input id="end_date" name="end_date" type="date" value="{{ $filters['end']->toDateString() }}" required></div>
<div class="field"><label for="type">Report data</label><select id="type" name="type"><option value="all" @selected($type==='all')>All financial data</option><option value="income" @selected($type==='income')>Income</option><option value="expenses" @selected($type==='expenses')>Expenses</option><option value="savings" @selected($type==='savings')>Savings</option><option value="budgets" @selected($type==='budgets')>Budgets</option></select></div>
<div><button class="btn" type="submit">Generate report</button></div></form><p class="muted" style="font-size:11px;margin:12px 0 0">Choose a date range of up to 366 days. Only your own records are included.</p></section>
<div class="grid stats" style="margin-bottom:18px">
@foreach(['Income'=>$incomeTotal,'Expenses'=>$expenseTotal,'Savings'=>$savingsTotal,'Budget limits'=>$budgetTotal,'Net balance'=>$netBalance] as $label=>$value)<section class="card"><div class="stat-label">{{ $label }}</div><div class="stat-value" style="font-size:20px">KES {{ number_format($value,2) }}</div></section>@endforeach
</div>
<section class="card" style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><div><h2 class="section-heading" style="margin-bottom:5px">Export report</h2><p class="muted" style="font-size:11px;margin:0">{{ $start->format('d M Y') }} – {{ $end->format('d M Y') }}</p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-light" href="{{ route('reports.csv',request()->query()) }}">Download CSV</a><a class="btn" href="{{ route('reports.pdf',request()->query()) }}">Download PDF</a></div></div>
<form method="POST" action="{{ route('reports.email') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:18px">@csrf
<input type="hidden" name="start_date" value="{{ $start->toDateString() }}"><input type="hidden" name="end_date" value="{{ $end->toDateString() }}"><input type="hidden" name="type" value="{{ $type }}">
<div class="field" style="min-width:240px;flex:1"><label for="email">Send PDF report to email</label><input id="email" name="email" type="email" maxlength="254" value="{{ old('email',auth()->user()->email) }}" required></div><button class="btn" type="submit">Queue email</button></form>
<p class="muted" style="font-size:11px;margin:9px 0 0">Email uses your configured mail transport and queue worker. A queued message is not a delivery confirmation.</p></section>
@foreach([['title'=>'Income','rows'=>$income,'label'=>'source'],['title'=>'Expenses','rows'=>$expenses,'label'=>'category'],['title'=>'Savings','rows'=>$savings,'label'=>'goal']] as $section)
<section class="card" style="margin-bottom:16px"><h2 class="section-heading">{{ $section['title'] }}</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Description</th><th>Amount (KES)</th></tr></thead><tbody>
@forelse($section['rows'] as $row)<tr><td>{{ $row->transaction_date->format('d M Y') }}</td><td>{{ $row->{$section['label']} }}</td><td class="amount">{{ number_format((float)$row->amount,2) }}</td></tr>@empty<tr><td colspan="3" class="muted">No {{ strtolower($section['title']) }} records in this period.</td></tr>@endforelse
</tbody></table></div></section>@endforeach
<section class="card"><h2 class="section-heading">Budget limits</h2><div class="table-wrap"><table><thead><tr><th>Month</th><th>Category</th><th>Limit (KES)</th></tr></thead><tbody>@forelse($budgets as $budget)<tr><td>{{ $budget->budget_month }}</td><td>{{ $budget->category }}</td><td class="amount">{{ number_format((float)$budget->amount,2) }}</td></tr>@empty<tr><td colspan="3" class="muted">No budget limits in this period.</td></tr>@endforelse</tbody></table></div></section>
@endsection
