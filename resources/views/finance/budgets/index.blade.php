@extends('layouts.app')
@section('title', 'Monthly budgets')
@section('heading', 'Monthly budgets')
@section('content')
<div class="grid stats" style="margin-bottom:18px">
    <div class="card"><div class="stat-label">Total budget</div><div class="stat-value">KES {{ number_format($budgetTotal, 2) }}</div><div class="stat-note">Selected month</div></div>
    <div class="card"><div class="stat-label">Spent in budgeted categories</div><div class="stat-value">KES {{ number_format($spentTotal, 2) }}</div><div class="stat-note">Matched to expense categories</div></div>
    <div class="card"><div class="stat-label">Remaining</div><div class="stat-value" style="color:{{ $remainingTotal < 0 ? '#b42318' : '#087443' }}">KES {{ number_format($remainingTotal, 2) }}</div><div class="stat-note">Budget less spending</div></div>
    <div class="card"><div class="stat-label">Budget categories</div><div class="stat-value">{{ $budgets->total() }}</div><div class="stat-note">For {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->format('F Y') }}</div></div>
</div>
<div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
        <h2 class="section-heading" style="margin:0">Monthly overview</h2>
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <label for="month" class="muted" style="font-size:12px">Month</label><input id="month" name="month" type="month" value="{{ $month }}" required>
            <button class="btn btn-small" type="submit">View month</button>
        </form>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-light btn-small" href="{{ route('budgets.index', ['month' => \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->subMonth()->format('Y-m')]) }}">← Previous month</a>
        <a class="btn btn-light btn-small" href="{{ route('budgets.index', ['month' => \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->addMonth()->format('Y-m')]) }}">Next month →</a>
    </div>
</div>
<div class="card" style="margin-bottom:18px">
    <h2 class="section-heading">{{ $editBudget ? 'Edit budget' : 'Add a budget category' }}</h2>
    <form method="POST" action="{{ $editBudget ? route('budgets.update', $editBudget) : route('budgets.store') }}" class="form-grid">
        @csrf
        @if($editBudget) @method('PUT')<input type="hidden" name="lock_version" value="{{ $editBudget->lock_version }}">@else
            <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
        @endif
        <div class="field"><label for="category">Category</label><input id="category" name="category" maxlength="50" value="{{ old('category', $editBudget?->category) }}" placeholder="e.g. Food" required></div>
        <div class="field"><label for="amount">Monthly limit (KES)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $editBudget?->amount) }}" required></div>
        <div class="field"><label for="date">Budget date</label><input id="date" name="date" type="date" value="{{ old('date', $editBudget?->date?->format('Y-m-d') ?? ($month.'-01')) }}" required></div>
        <div><button class="btn" type="submit">{{ $editBudget ? 'Save changes' : '+ Add budget' }}</button>
            @if($editBudget)<a class="btn btn-light" href="{{ route('budgets.index', ['month' => $month]) }}">Cancel</a>@endif
        </div>
    </form>
</div>
<div class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Copy budgets to another month</h2>
    <form method="POST" action="{{ route('budgets.copy') }}" class="form-grid">
        @csrf
        <div class="field"><label for="from_month">Copy from</label><input id="from_month" name="from_month" type="month" value="{{ old('from_month', \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->subMonth()->format('Y-m')) }}" required></div>
        <div class="field"><label for="to_month">Copy to</label><input id="to_month" name="to_month" type="month" value="{{ old('to_month', $month) }}" required></div>
        <div class="field"><label for="strategy">If categories already exist</label><select id="strategy" name="strategy" required><option value="skip">Skip existing categories</option><option value="report">Report duplicates; copy nothing</option><option value="replace">Replace all target budgets</option></select></div>
        <div class="field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="confirm_replace" value="1" style="width:auto;height:auto"> Confirm replacement (required for replace)</label><button class="btn btn-small" type="submit">Copy budgets</button></div>
    </form>
    <p class="muted" style="font-size:11px;margin:10px 0 0">Replace deletes every budget in the target month before copying. Use it only when you intend to replace the complete target-month plan.</p>
</div>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
        <h2 class="section-heading" style="margin:0">Budget categories</h2>
        <form method="GET" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="month" value="{{ $month }}">
            <select name="category" aria-label="Filter category"><option value="">All categories</option>@foreach($categories as $item)<option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>@endforeach</select>
            <button class="btn btn-small" type="submit">Filter</button>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Category</th><th>Budget (KES)</th><th>Spent (KES)</th><th>Remaining (KES)</th><th>Usage</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($budgets as $budget)
            @php($usage = $budget->utilization)
            <tr>
                <td><strong>{{ $budget->category }}</strong></td><td class="amount">{{ number_format((float) $budget->amount, 2) }}</td>
                <td>{{ number_format((float) $budget->spent, 2) }}</td>
                <td style="color:{{ $budget->remaining < 0 ? '#b42318' : '#087443' }}">KES {{ number_format($budget->remaining, 2) }}</td>
                <td style="min-width:130px"><div style="height:7px;background:#edf0f5;border-radius:9px;overflow:hidden"><div style="height:100%;width:{{ min(100, max(0, $usage)) }}%;background:{{ $usage >= 100 ? '#b42318' : ($usage >= 80 ? '#d97706' : '#25845b') }};border-radius:9px"></div></div><small>{{ number_format($usage, 1) }}%{{ $usage >= 100 ? ' · Over budget' : ($usage >= 80 ? ' · Near limit' : '') }}</small></td>
                <td>{{ $budget->date?->format('d M Y') ?? '—' }}</td>
                <td><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="btn btn-light btn-small" href="{{ route('budgets.index', ['month' => $month, 'edit' => $budget->id]) }}">Edit</a>
                    <form method="POST" action="{{ route('budgets.destroy', $budget) }}" onsubmit="return confirm('Delete this budget?')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $budget->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete</button></form></div></td>
            </tr>
        @empty<tr><td colspan="7" class="muted">No budget categories recorded for this month.</td></tr>@endforelse
        </tbody>
    </table></div>
    {{ $budgets->links('pagination::simple-tailwind') }}
</div>
@endsection
