@extends('layouts.app')
@section('title', 'Review CSV import')
@section('heading', 'Review CSV import')
@section('content')
<div class="grid stats" style="margin-bottom:18px">
    <div class="card"><div class="stat-label">Transactions to import</div><div class="stat-value">{{ count($rows) }}</div></div>
    <div class="card"><div class="stat-label">Income records</div><div class="stat-value"> {{ $incomeCount }}</div></div>
    <div class="card"><div class="stat-label">Expense records</div><div class="stat-value">{{ $expenseCount }}</div></div>
    <div class="card"><div class="stat-label">Combined amount</div><div class="stat-value">KES {{ number_format($total, 2) }}</div></div>
</div>
<div class="card">
    <h2 class="section-heading">Check every row before importing</h2>
    <p class="muted" style="font-size:12px">Nothing has been saved yet. Confirming will add all {{ count($rows) }} validated rows to your account in one database transaction.</p>
    <div class="table-wrap"><table><thead><tr><th>Type</th><th>Date</th><th>Description</th><th>Category</th><th>Tag</th><th>Amount</th></tr></thead><tbody>
    @foreach($rows as $row)
    <tr><td><span class="badge">{{ ucfirst($row['type']) }}</span></td><td>{{ $row['date'] }}</td><td>{{ $row['description'] }}</td><td>{{ $row['category'] ?: '—' }}</td><td>{{ $row['tag'] ?: '—' }}</td><td class="amount">KES {{ number_format((float) $row['amount'], 2) }}</td></tr>
    @endforeach
    </tbody></table></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
        <form method="POST" action="{{ route('imports.confirm', $batch) }}">@csrf<button class="btn" type="submit">Confirm and import {{ count($rows) }} transactions</button></form>
        <form method="POST" action="{{ route('imports.cancel', $batch) }}">@csrf @method('DELETE')<button class="btn btn-light" type="submit">Discard preview</button></form>
    </div>
</div>
@endsection
