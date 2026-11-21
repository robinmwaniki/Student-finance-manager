@extends('layouts.app')
@section('title','Expenses')
@section('heading','Expenses')
@section('content')
<div class="card" style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><div class="eyebrow">Monthly spending</div><div class="stat-value">KES {{ number_format((float)$total,2) }}</div></div><form method="GET" class="field" style="display:flex;align-items:end;gap:8px"><div><label for="month">Month</label><input id="month" type="month" name="month" value="{{ $month }}"></div><button class="btn" type="submit">Apply</button></form></div></div>
<div class="card" style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><h2 class="section-heading">Add expense</h2><a class="btn btn-light" href="{{ route('tools.index') }}">Financial tools</a></div>
<form method="POST" action="{{ route('expenses.store') }}" class="form-grid">@csrf<input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
<div class="field"><label for="category">Category</label><input id="category" name="category" maxlength="50" placeholder="e.g. Food, Transport" value="{{ old('category') }}" required></div>
<div class="field"><label for="amount">Original amount</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" placeholder="0.00" value="{{ old('amount') }}" required></div>
<div class="field"><label for="currency">Currency</label><select id="currency" name="currency">@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(old('currency', 'KES') === $currency)>{{ $currency }}</option>@endforeach</select><small id="currency-rate-hint" class="muted">KES entries use a 1:1 rate. Foreign currency is converted to KES using a timestamped public rate.</small></div>
<div class="field"><label for="transaction_date">Date paid</label><input id="transaction_date" name="transaction_date" type="date" max="{{ now(config('app.timezone'))->toDateString() }}" value="{{ old('transaction_date',now(config('app.timezone'))->toDateString()) }}" required></div>
<div class="field"><label for="expense-wallet">Pay from wallet</label><select id="expense-wallet" name="wallet_id" required>@foreach($wallets as $wallet)<option value="{{ $wallet->id }}" @selected(old('wallet_id', $wallet->is_default ? $wallet->id : null) == $wallet->id)>{{ $wallet->name }}</option>@endforeach</select></div>
<div class="field"><label for="tag">Tag (optional)</label><input id="tag" name="tag" maxlength="50" placeholder="e.g. Campus"></div>
<div><button class="btn" type="submit">+ Save expense</button></div>
@if(session('stale_expense_quote'))<label style="grid-column:1/-1;font-size:11px;color:#9a5b0a"><input type="checkbox" name="accept_stale_rate" value="1"> Accept cached rate: 1 {{ session('stale_expense_quote.from') }} = {{ number_format(session('stale_expense_quote.rate'),6) }} KES (updated {{ session('stale_expense_quote.updated_at') }})</label>@endif
</form></div>
<div class="card"><h2 class="section-heading">Expense records</h2><div class="table-wrap"><table><thead><tr><th>Category</th><th>Date</th><th>Tag</th><th>Amount</th><th>Manage</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td><strong>{{ $record->category }}</strong></td><td>{{ $record->transaction_date->format('d M Y') }}</td><td>{{ $record->tag ?: '—' }}</td><td class="amount">KES {{ number_format((float)$record->amount,2) }}@if($record->original_currency && $record->original_currency !== 'KES')<small class="muted" style="display:block">{{ number_format((float)$record->original_amount,2) }} {{ $record->original_currency }} × {{ number_format((float)$record->exchange_rate,6) }} · {{ $record->exchange_rate_updated_at?->format('d M Y H:i') }} · {{ $record->exchange_rate_source }}</small>@endif</td><td><details><summary style="cursor:pointer;color:#5365b5;font-weight:700">Edit</summary>
<form method="POST" action="{{ route('expenses.update',$record) }}" style="min-width:210px;padding:12px 0">@csrf @method('PUT')<input type="hidden" name="lock_version" value="{{ $record->lock_version }}">
<div class="field" style="margin-bottom:8px"><label>Category</label><input name="category" value="{{ $record->category }}" maxlength="50" required></div>
<div class="field" style="margin-bottom:8px"><label>Amount (KES)</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ $record->amount }}" required></div>
<div class="field" style="margin-bottom:8px"><label>Date</label><input name="transaction_date" type="date" value="{{ $record->transaction_date->format('Y-m-d') }}" required></div>
<div class="field" style="margin-bottom:8px"><label>Tag</label><input name="tag" value="{{ $record->tag }}"></div><button class="btn btn-small" type="submit">Update</button></form>
<form method="POST" action="{{ route('expenses.destroy',$record) }}" onsubmit="return confirm('Delete this expense record?')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $record->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete</button></form>
</details></td></tr>
@empty<tr><td colspan="5" class="muted">No expense records for this month.</td></tr>@endforelse
</tbody></table></div>{{ $records->links('pagination::simple-tailwind') }}</div>
@endsection

@push('scripts')
<script>
(() => {
    const select = document.getElementById('currency');
    const hint = document.getElementById('currency-rate-hint');
    if (!select || !hint) return;
    const endpoint = @json(route('tools.rate'));
    const refreshRate = async () => {
        if (select.value === 'KES') {
            hint.textContent = '1 KES = 1 KES. The amount is stored without conversion.';
            return;
        }
        hint.textContent = 'Loading latest reference rate…';
        try {
            const response = await fetch(endpoint + '?from=' + encodeURIComponent(select.value) + '&to=KES', { headers: { Accept: 'application/json' } });
            const quote = await response.json();
            if (!response.ok) throw new Error(quote.message || 'Rate unavailable');
            hint.textContent = '1 ' + quote.from + ' = ' + Number(quote.rate).toFixed(6) + ' KES · Updated ' + quote.updated_at + (quote.stale ? ' · CACHED / STALE' : ' · ' + quote.source);
        } catch (error) {
            hint.textContent = 'Exchange rate unavailable. You can still record this expense in KES.';
        }
    };
    select.addEventListener('change', refreshRate);
    refreshRate();
})();
</script>
@endpush

