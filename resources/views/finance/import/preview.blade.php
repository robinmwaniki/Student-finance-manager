@extends('layouts.app')
@section('title', 'Review transaction import')
@section('heading', 'Review transaction import')
@section('content')
<div class="grid stats" style="margin-bottom:18px">
    <div class="card"><div class="stat-label">Transactions to import</div><div class="stat-value">{{ count($rows) }}</div></div>
    <div class="card"><div class="stat-label">Income records</div><div class="stat-value">{{ $incomeCount }}</div></div>
    <div class="card"><div class="stat-label">Expense records</div><div class="stat-value">{{ $expenseCount }}</div></div>
    <div class="card"><div class="stat-label">Combined amount</div><div class="stat-value">KES {{ number_format($total, 2) }}</div></div>
</div>
<div class="card">
    <h2 class="section-heading">Review and categorise your transactions</h2>
    <p class="muted" style="font-size:12px">Nothing has been saved yet. Check the date, description, type and amount. Expense categories are suggestions and can be changed below. Confirming adds all {{ count($rows) }} validated rows to your account in one database transaction.</p>
    <form method="POST" action="{{ route('imports.confirm', $batch) }}">
        @csrf
        <div class="table-wrap"><table><thead><tr><th>Type</th><th>Date</th><th>Description</th><th>Category</th><th>Tag</th><th>Amount</th></tr></thead><tbody>
        @foreach($rows as $index => $row)
        <tr>
            <td><span class="badge">{{ ucfirst($row['type']) }}</span></td>
            <td>{{ $row['date'] }}</td>
            <td>{{ $row['description'] }}</td>
            <td>
                @if($row['type'] === 'expense')
                    <select name="categories[{{ $index }}]" aria-label="Category for {{ $row['description'] }}" style="min-width:145px;max-width:190px">
                        @if(!in_array($row['category'], $categories, true))
                            <option value="{{ $row['category'] }}" selected>{{ $row['category'] }}</option>
                        @endif
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected($row['category'] === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                @else
                    —
                @endif
            </td>
            <td>{{ $row['tag'] ?: '—' }}</td>
            <td class="amount">KES {{ number_format((float) $row['amount'], 2) }}</td>
        </tr>
        @endforeach
        </tbody></table></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
            <button class="btn" type="submit">Confirm and import {{ count($rows) }} transactions</button>
        </div>
    </form>
    <form method="POST" action="{{ route('imports.cancel', $batch) }}" style="margin-top:10px">
        @csrf
        @method('DELETE')
        <button class="btn btn-light" type="submit">Discard preview</button>
    </form>
</div>
@endsection
