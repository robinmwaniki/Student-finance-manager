@extends('layouts.app')
@section('title', 'Bill reminders')
@section('heading', 'Bill reminders')
@section('content')
<div class="grid stats" style="margin-bottom:18px">
    <div class="card"><div class="stat-label">Active</div><div class="stat-value">{{ $summary['active'] }}</div></div>
    <div class="card"><div class="stat-label">Paused</div><div class="stat-value">{{ $summary['paused'] }}</div></div>
    <div class="card"><div class="stat-label">Completed</div><div class="stat-value">{{ $summary['completed'] }}</div></div>
    <div class="card"><div class="stat-label">Total reminders</div><div class="stat-value">{{ $reminders->total() }}</div></div>
</div>
<div class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Create a bill reminder</h2>
    <form method="POST" action="{{ route('reminders.store') }}" class="form-grid">@csrf
        <div class="field"><label for="title">Bill / reminder name</label><input id="title" name="title" maxlength="150" value="{{ old('title') }}" placeholder="e.g. Electricity bill" required></div>
        <div class="field"><label for="category">Category</label><input id="category" name="category" maxlength="50" value="{{ old('category') }}" placeholder="Utilities"></div>
        <div class="field"><label for="amount">Amount (KES)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required></div>
        <div class="field"><label for="due_date">Next due date</label><input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" required></div>
        <div class="field"><label for="recurrence">Recurrence</label><select id="recurrence" name="recurrence" required><option value="once">One-time</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
        <div><button class="btn" type="submit">+ Save reminder</button></div>
    </form>
</div>
<div class="card">
    <h2 class="section-heading">Your reminders</h2>
    <div class="table-wrap"><table><thead><tr><th>Reminder</th><th>Amount</th><th>Next due</th><th>Recurrence</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($reminders as $reminder)
        <tr>
            <td><strong>{{ $reminder->title }}</strong><div class="muted" style="font-size:11px">{{ $reminder->category ?: 'Uncategorized' }}</div></td>
            <td class="amount">KES {{ number_format((float) $reminder->amount, 2) }}</td>
            <td>{{ $reminder->due_date->format('d M Y') }}</td>
            <td>{{ ucfirst($reminder->recurrence) }}</td>
            <td>{{ ucfirst($reminder->status) }}</td>
            <td><details><summary style="cursor:pointer;color:#5365b5;font-weight:700">Manage</summary>
                <div style="min-width:240px;padding:10px 0">
                    @if($reminder->status !== 'completed')
                    <form method="POST" action="{{ route('reminders.update', $reminder) }}" style="margin-bottom:10px">@csrf @method('PUT')
                        <input type="hidden" name="lock_version" value="{{ $reminder->lock_version }}">
                        <div class="field" style="margin-bottom:7px"><label>Name</label><input name="title" value="{{ $reminder->title }}" maxlength="150" required></div>
                        <div class="field" style="margin-bottom:7px"><label>Category</label><input name="category" value="{{ $reminder->category }}" maxlength="50"></div>
                        <div class="field" style="margin-bottom:7px"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ $reminder->amount }}" required></div>
                        <div class="field" style="margin-bottom:7px"><label>Due date</label><input name="due_date" type="date" value="{{ $reminder->due_date->format('Y-m-d') }}" required></div>
                        <div class="field" style="margin-bottom:7px"><label>Recurrence</label><select name="recurrence"><option value="once" @selected($reminder->recurrence === 'once')>One-time</option><option value="weekly" @selected($reminder->recurrence === 'weekly')>Weekly</option><option value="monthly" @selected($reminder->recurrence === 'monthly')>Monthly</option><option value="yearly" @selected($reminder->recurrence === 'yearly')>Yearly</option></select></div>
                        <button class="btn btn-small" type="submit">Save changes</button>
                    </form>
                    <form method="POST" action="{{ route('reminders.toggle', $reminder) }}" style="margin-bottom:8px">@csrf @method('PATCH')<button class="btn btn-light btn-small" type="submit">{{ $reminder->status === 'active' ? 'Pause' : 'Resume' }}</button></form>
                    @endif
                    <form method="POST" action="{{ route('reminders.destroy', $reminder) }}" onsubmit="return confirm('Delete this reminder?')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $reminder->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete</button></form>
                </div>
            </details></td>
        </tr>
    @empty<tr><td colspan="6" class="muted">No bill reminders created yet.</td></tr>@endforelse
    </tbody></table></div>
    {{ $reminders->links('pagination::simple-tailwind') }}
</div>
@endsection
