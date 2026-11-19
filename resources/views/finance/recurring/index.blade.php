@extends('layouts.app')
@section('title', 'Recurring transactions')
@section('heading', 'Recurring transactions')
@section('content')
<div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div><h2 class="section-heading" style="margin-bottom:5px">Scheduled income and expenses</h2><p class="muted" style="font-size:12px;margin:0">Due schedules are applied to your ledger only when you run processing.</p></div>
        <form method="POST" action="{{ route('recurring.process') }}">@csrf<button class="btn" type="submit">Process due transactions</button></form>
    </div>
</div>
<div class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Add recurring transaction</h2>
    <form method="POST" action="{{ route('recurring.store') }}" class="form-grid">
        @csrf
        <div class="field"><label for="label">Name / description</label><input id="label" name="label" maxlength="150" placeholder="e.g. Monthly rent"></div>
        <div class="field"><label for="type">Transaction type</label><select id="type" name="type" required><option value="expense">Expense</option><option value="income">Income</option></select></div>
        <div class="field"><label for="category">Expense category</label><input id="category" name="category" maxlength="50" placeholder="e.g. Rent"></div>
        <div class="field"><label for="amount">Amount (KES)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required></div>
        <div class="field"><label for="frequency">Frequency</label><select id="frequency" name="frequency" required><option value="monthly">Monthly</option><option value="weekly">Weekly</option><option value="daily">Daily</option></select></div>
        <div class="field"><label for="next_due">First due date</label><input id="next_due" name="next_due" type="date" required></div>
        <div class="field"><label for="day_of_month">Monthly day (1–31)</label><input id="day_of_month" name="day_of_month" type="number" min="1" max="31" placeholder="Uses first due date"></div>
        <div><button class="btn" type="submit">Save schedule</button></div>
    </form>
</div>
<div class="card">
    <h2 class="section-heading">Your schedules</h2>
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Type</th><th>Amount</th><th>Frequency</th><th>Next due</th><th>Status</th><th>History</th><th>Last applied</th><th>Actions</th></tr></thead><tbody>
    @forelse($records as $record)
        <tr>
            <td><strong>{{ $record->label ?: ($record->category ?: ucfirst($record->type)) }}</strong></td>
            <td>{{ ucfirst($record->type) }}</td><td class="amount">KES {{ number_format((float) $record->amount, 2) }}</td>
            <td>{{ ucfirst($record->frequency) }}</td><td>{{ $record->next_due?->format('d M Y') ?? '—' }}</td>
            <td>{{ $record->active ? 'Active' : 'Paused' }}</td><td>{{ $record->logs_count }} applied</td><td>{{ $record->logs_max_applied_at ? \Illuminate\Support\Carbon::parse($record->logs_max_applied_at)->format('d M Y H:i') : 'Never' }}</td>
            <td><div style="display:flex;gap:6px;flex-wrap:wrap">
                <form method="POST" action="{{ route('recurring.toggle', $record) }}">@csrf @method('PATCH')<button class="btn btn-small btn-light" type="submit">{{ $record->active ? 'Pause' : 'Resume' }}</button></form>
                <form method="POST" action="{{ route('recurring.process') }}">@csrf<input type="hidden" name="recurring_id" value="{{ $record->id }}"><button class="btn btn-small" type="submit" {{ !$record->active ? 'disabled' : '' }}>Process</button></form>
                @if($record->active)<details><summary style="cursor:pointer;color:#5365b5;font-weight:700">Edit</summary>
                    <form method="POST" action="{{ route('recurring.update', $record) }}" style="min-width:230px;padding:10px 0">@csrf @method('PUT')
                        <div class="field" style="margin-bottom:7px"><label>Name</label><input name="label" maxlength="150" value="{{ $record->label }}"></div>
                        <div class="field" style="margin-bottom:7px"><label>Type</label><select name="type"><option value="income" @selected($record->type === 'income')>Income</option><option value="expense" @selected($record->type === 'expense')>Expense</option></select></div>
                        <div class="field" style="margin-bottom:7px"><label>Category (expenses)</label><input name="category" maxlength="50" value="{{ $record->category }}"></div>
                        <div class="field" style="margin-bottom:7px"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ $record->amount }}" required></div>
                        <div class="field" style="margin-bottom:7px"><label>Frequency</label><select name="frequency"><option value="daily" @selected($record->frequency === 'daily')>Daily</option><option value="weekly" @selected($record->frequency === 'weekly')>Weekly</option><option value="monthly" @selected($record->frequency === 'monthly')>Monthly</option></select></div>
                        <div class="field" style="margin-bottom:7px"><label>Next due</label><input name="next_due" type="date" value="{{ $record->next_due?->format('Y-m-d') }}" required></div>
                        <div class="field" style="margin-bottom:7px"><label>Monthly day (1–31)</label><input name="day_of_month" type="number" min="1" max="31" value="{{ $record->day_of_month }}"></div>
                        <button class="btn btn-small" type="submit">Save changes</button>
                    </form></details>@endif
                @if($record->logs_count === 0)<form method="POST" action="{{ route('recurring.destroy', $record) }}" onsubmit="return confirm('Delete this schedule?')">@csrf @method('DELETE')<button class="btn btn-small btn-danger" type="submit">Delete</button></form>@endif
            </div></td>
        </tr>
    @empty<tr><td colspan="9" class="muted">No recurring schedules yet.</td></tr>@endforelse
    </tbody></table></div>
    {{ $records->links('pagination::simple-tailwind') }}
</div>
@endsection
