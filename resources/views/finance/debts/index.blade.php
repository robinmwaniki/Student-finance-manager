@extends('layouts.app')
@section('title','Debt tracking')
@section('heading','Debt tracking')
@section('content')
<div class="grid stats" style="margin-bottom:18px">
<div class="card"><div class="stat-label">You owe</div><div class="stat-value">KES {{ number_format((float) $summary['borrowed'], 2) }}</div><div class="stat-note">Outstanding borrowed debt</div></div>
<div class="card"><div class="stat-label">Owed to you</div><div class="stat-value">KES {{ number_format((float) $summary['lent'], 2) }}</div><div class="stat-note">Outstanding money lent</div></div>
<div class="card"><div class="stat-label">Overdue records</div><div class="stat-value">{{ $summary['overdueCount'] }}</div><div class="stat-note">Pending debts past due date</div></div>
<div class="card"><div class="stat-label">Debt records</div><div class="stat-value">{{ $debts->total() }}</div><div class="stat-note">Matching current filters</div></div>
</div>
<div class="card" style="margin-bottom:18px"><h2 class="section-heading">Add debt record</h2>
<form method="POST" action="{{ route('debts.store') }}" class="form-grid">@csrf
<input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
<div class="field"><label for="description">Description</label><input id="description" name="description" maxlength="255" value="{{ old('description') }}" placeholder="e.g. Student loan"></div>
<div class="field"><label for="amount">Amount (KES)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required></div>
<div class="field"><label for="type">Debt direction</label><select id="type" name="type" required><option value="borrowed" @selected(old('type') === 'borrowed')>I owe (borrowed)</option><option value="lent" @selected(old('type') === 'lent')>Owed to me (lent)</option></select></div>
<div class="field"><label for="due_date">Due date</label><input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}"></div>
<div><button class="btn" type="submit">+ Save debt</button></div>
</form></div>
<div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><h2 class="section-heading" style="margin:0">Debt records</h2>
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap"><select name="type" aria-label="Filter debt type"><option value="">All types</option><option value="borrowed" @selected($type === 'borrowed')>I owe</option><option value="lent" @selected($type === 'lent')>Owed to me</option></select><select name="status" aria-label="Filter debt status"><option value="all" @selected($status === 'all')>All statuses</option><option value="pending" @selected($status === 'pending')>Pending</option><option value="paid" @selected($status === 'paid')>Paid</option><option value="overdue" @selected($status === 'overdue')>Overdue</option></select><button class="btn btn-small" type="submit">Filter</button></form></div>
<div class="table-wrap"><table><thead><tr><th>Description</th><th>Direction</th><th>Due date</th><th>Status</th><th>Amount</th><th>Manage</th></tr></thead><tbody>
@forelse($debts as $debt)
@php($overdue = $debt->status === 'pending' && $debt->due_date && $debt->due_date->lt(today()))
<tr><td><strong>{{ $debt->description ?: 'Debt record' }}</strong></td><td>{{ $debt->type === 'borrowed' ? 'I owe' : ($debt->type === 'lent' ? 'Owed to me' : ucfirst($debt->type)) }}</td><td>{{ $debt->due_date?->format('d M Y') ?? '—' }}</td><td>{{ $overdue ? 'Overdue' : ucfirst($debt->status) }}</td><td class="amount">KES {{ number_format((float) $debt->amount, 2) }}</td><td><details><summary style="cursor:pointer;color:#5365b5;font-weight:700">Edit</summary>
<form method="POST" action="{{ route('debts.update', $debt) }}" style="min-width:220px;padding:12px 0">@csrf @method('PUT')<input type="hidden" name="lock_version" value="{{ $debt->lock_version }}">
<div class="field" style="margin-bottom:8px"><label>Description</label><input name="description" value="{{ $debt->description }}" maxlength="255"></div>
<div class="field" style="margin-bottom:8px"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ $debt->amount }}" required></div>
<div class="field" style="margin-bottom:8px"><label>Direction</label><select name="type"><option value="borrowed" @selected($debt->type === 'borrowed')>I owe</option><option value="lent" @selected($debt->type === 'lent')>Owed to me</option></select></div>
<div class="field" style="margin-bottom:8px"><label>Due date</label><input name="due_date" type="date" value="{{ $debt->due_date?->format('Y-m-d') }}"></div>
<div class="field" style="margin-bottom:8px"><label>Status</label><select name="status"><option value="pending" @selected($debt->status === 'pending')>Pending</option><option value="paid" @selected($debt->status === 'paid')>Paid / settled</option></select></div>
<button class="btn btn-small" type="submit">Update</button></form>
<form method="POST" action="{{ route('debts.destroy', $debt) }}" onsubmit="return confirm('Delete this debt record?')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $debt->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete</button></form>
</details></td></tr>
@empty<tr><td colspan="6" class="muted">No debt records match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $debts->links('pagination::simple-tailwind') }}</div>
@endsection
