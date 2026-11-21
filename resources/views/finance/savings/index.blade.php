@extends('layouts.app')
@section('title','Savings')
@section('heading','Savings')
@section('content')
<div class="card" style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><div class="eyebrow">Monthly contributions</div><div class="stat-value">KES {{ number_format((float) $monthlyTotal, 2) }}</div></div><form method="GET" class="field" style="display:flex;align-items:end;gap:8px"><div><label for="month">Month</label><input id="month" type="month" name="month" value="{{ $month }}"></div><button class="btn" type="submit">Apply</button></form></div></div>
<div class="grid two-col">
<section class="card"><h2 class="section-heading">Create a savings goal</h2><form method="POST" action="{{ route('savings.goals.store') }}" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">@csrf
<div class="field"><label for="goal-title">Goal name</label><input id="goal-title" name="title" maxlength="100" placeholder="e.g. Laptop" required></div>
<div class="field"><label for="goal-target">Target (KES)</label><input id="goal-target" name="target_amount" type="number" min="0.01" step="0.01" placeholder="Optional"></div>
<div class="field"><label for="goal-due">Target date</label><input id="goal-due" name="due_date" type="date" min="{{ now(config('app.timezone'))->toDateString() }}"></div>
<div class="field"><label for="goal-notes">Notes</label><input id="goal-notes" name="notes" maxlength="500" placeholder="Optional"></div><div><button class="btn" type="submit">+ Create goal</button></div></form></section>
<section class="card"><h2 class="section-heading">Record a contribution</h2><form method="POST" action="{{ route('savings.store') }}" class="grid" style="grid-template-columns:1fr 1fr;gap:12px">@csrf
<input type="hidden" name="submission_token" value="{{ $submissionToken }}">
<div class="field" style="grid-column:1/-1"><label for="contribution-goal">Savings goal</label><select id="contribution-goal" name="goal_id" onchange="document.getElementById('custom-goal').hidden=!!this.value"><option value="">General savings</option>@foreach($goals as $goal)<option value="{{ $goal->id }}">{{ $goal->title }}</option>@endforeach</select></div>
<div class="field" id="custom-goal"><label for="contribution-label">Description</label><input id="contribution-label" name="goal" maxlength="100" placeholder="e.g. Emergency fund"></div>
<div class="field"><label for="contribution-amount">Amount (KES)</label><input id="contribution-amount" name="amount" type="number" min="0.01" step="0.01" required></div>
<div class="field"><label for="contribution-date">Date saved</label><input id="contribution-date" name="transaction_date" type="date" max="{{ now(config('app.timezone'))->toDateString() }}" value="{{ now(config('app.timezone'))->toDateString() }}" required></div>
<div><button class="btn" type="submit">+ Save contribution</button></div></form></section>
</div>
<section class="card" style="margin-top:18px"><h2 class="section-heading">Your goals</h2><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px">
@forelse($goals as $goal) @php($progress = $goal->target_amount ? min(100, (float) $goal->current_amount / (float) $goal->target_amount * 100) : null)
<article style="border:1px solid #edf0f5;border-radius:10px;padding:15px"><div style="display:flex;justify-content:space-between;gap:10px"><strong>{{ $goal->title }}</strong><span class="muted" style="font-size:11px">{{ ucfirst($goal->status) }}</span></div>
<div style="font-size:12px;margin-top:9px">KES {{ number_format((float) $goal->current_amount, 2) }} @if($goal->target_amount)/ {{ number_format((float) $goal->target_amount, 2) }}@else saved @endif</div>
@if($progress !== null)<div style="height:7px;background:#edf0f5;border-radius:8px;margin:10px 0;overflow:hidden"><div style="height:100%;width:{{ $progress }}%;background:#526b9b;border-radius:8px"></div></div><div class="muted" style="font-size:11px">{{ number_format($progress, 1) }}% complete</div>@endif
@if($goal->due_date)<div class="muted" style="font-size:11px;margin-top:7px">Due {{ $goal->due_date->format('d M Y') }}</div>@endif
<details style="margin-top:12px"><summary style="cursor:pointer;color:#5365b5;font-size:12px;font-weight:700">Manage goal</summary>
<form method="POST" action="{{ route('savings.goals.update', $goal) }}" class="grid" style="gap:8px;padding:10px 0">@csrf @method('PUT')<input type="hidden" name="lock_version" value="{{ $goal->lock_version }}">
<div class="field"><label>Title</label><input name="title" value="{{ $goal->title }}" maxlength="100" required></div><div class="field"><label>Target amount</label><input name="target_amount" type="number" min="0.01" step="0.01" value="{{ $goal->target_amount }}"></div>
<div class="field"><label>Due date</label><input name="due_date" type="date" value="{{ $goal->due_date?->format('Y-m-d') }}"></div><div class="muted" style="font-size:11px;align-self:center">Status is calculated from contributions and the target amount.</div>
<div class="field"><label>Notes</label><input name="notes" value="{{ $goal->notes }}" maxlength="500"></div><button class="btn btn-small" type="submit">Update goal</button></form>
<form method="POST" action="{{ route('savings.goals.destroy', $goal) }}" onsubmit="return confirm('Delete this goal? Contributions will remain in your history.')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $goal->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete goal</button></form></details></article>
@empty<p class="muted" style="font-size:12px">No savings goals yet. Create one above to track progress.</p>@endforelse</div></section>
<section class="card" style="margin-top:18px"><h2 class="section-heading">Contribution history</h2><div class="table-wrap"><table><thead><tr><th>Description / goal</th><th>Date</th><th>Amount</th><th>Manage</th></tr></thead><tbody>
@forelse($records as $record)<tr><td><strong>{{ $record->goal }}</strong>@if($record->savingsGoal)<div class="muted" style="font-size:10px">Linked goal</div>@endif</td><td>{{ $record->transaction_date->format('d M Y') }}</td><td class="amount">KES {{ number_format((float) $record->amount, 2) }}</td><td><details><summary style="cursor:pointer;color:#5365b5;font-weight:700">Edit</summary>
<form method="POST" action="{{ route('savings.update', $record) }}" class="grid" style="gap:8px;min-width:210px;padding:10px 0">@csrf @method('PUT')<input type="hidden" name="lock_version" value="{{ $record->lock_version }}">
<div class="field"><label>Goal</label><select name="goal_id"><option value="">General savings</option>@foreach($goals as $goal)<option value="{{ $goal->id }}" @selected($record->goal_id === $goal->id)>{{ $goal->title }}</option>@endforeach</select></div>
<div class="field"><label>Description (when not linked)</label><input name="goal" value="{{ $record->goal }}" maxlength="100"></div><div class="field"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ $record->amount }}" required></div>
<div class="field"><label>Date</label><input name="transaction_date" type="date" value="{{ $record->transaction_date->format('Y-m-d') }}" required></div><button class="btn btn-small" type="submit">Update</button></form>
<form method="POST" action="{{ route('savings.destroy', $record) }}" onsubmit="return confirm('Delete this contribution?')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $record->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete</button></form>
</details></td></tr>
@empty<tr><td colspan="4" class="muted">No savings contributions for this month.</td></tr>@endforelse</tbody></table></div>{{ $records->links('pagination::simple-tailwind') }}</section>
@endsection
