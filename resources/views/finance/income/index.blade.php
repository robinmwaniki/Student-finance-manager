@extends('layouts.app')
@section('title','Income')
@section('heading','Income')
@section('content')
<div class="card" style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><div class="eyebrow">Monthly received</div><div class="stat-value">KES {{ number_format((float)$total,2) }}</div></div><form method="GET" class="field" style="display:flex;align-items:end;gap:8px"><div><label for="month">Month</label><input id="month" type="month" name="month" value="{{ $month }}"></div><button class="btn" type="submit">Apply</button></form></div></div>
<div class="card" style="margin-bottom:18px"><h2 class="section-heading">Add income</h2>
<form method="POST" action="{{ route('income.store') }}" class="form-grid">@csrf<input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
<div class="field"><label for="source">Income source</label><input id="source" name="source" maxlength="100" placeholder="e.g. Allowance, salary" value="{{ old('source') }}" required></div>
<div class="field"><label for="amount">Amount (KES)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" placeholder="0.00" value="{{ old('amount') }}" required></div>
<div class="field"><label for="transaction_date">Date received</label><input id="transaction_date" name="transaction_date" type="date" max="{{ now(config('app.timezone'))->toDateString() }}" value="{{ old('transaction_date',now(config('app.timezone'))->toDateString()) }}" required></div>
<div class="field"><label for="tag">Tag (optional)</label><input id="tag" name="tag" maxlength="50" placeholder="e.g. Part-time"></div>
<div><button class="btn" type="submit">+ Save income</button></div>
</form></div>
<div class="card"><h2 class="section-heading">Income records</h2><div class="table-wrap"><table><thead><tr><th>Source</th><th>Date</th><th>Tag</th><th>Amount</th><th>Manage</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td><strong>{{ $record->source }}</strong></td><td>{{ $record->transaction_date->format('d M Y') }}</td><td>{{ $record->tag ?: '—' }}</td><td class="amount">KES {{ number_format((float)$record->amount,2) }}</td><td><details><summary style="cursor:pointer;color:#5365b5;font-weight:700">Edit</summary>
<form method="POST" action="{{ route('income.update',$record) }}" style="min-width:210px;padding:12px 0">@csrf @method('PUT')<input type="hidden" name="lock_version" value="{{ $record->lock_version }}">
<div class="field" style="margin-bottom:8px"><label>Source</label><input name="source" value="{{ $record->source }}" maxlength="100" required></div>
<div class="field" style="margin-bottom:8px"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ $record->amount }}" required></div>
<div class="field" style="margin-bottom:8px"><label>Date</label><input name="transaction_date" type="date" value="{{ $record->transaction_date->format('Y-m-d') }}" required></div>
<div class="field" style="margin-bottom:8px"><label>Tag</label><input name="tag" value="{{ $record->tag }}"></div><button class="btn btn-small" type="submit">Update</button></form>
<form method="POST" action="{{ route('income.destroy',$record) }}" onsubmit="return confirm('Delete this income record?')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $record->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete</button></form>
</details></td></tr>
@empty<tr><td colspan="5" class="muted">No income records for this month.</td></tr>@endforelse
</tbody></table></div>{{ $records->links('pagination::simple-tailwind') }}</div>
@endsection
