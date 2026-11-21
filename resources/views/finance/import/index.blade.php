@extends('layouts.app')
@section('title', 'Import transactions')
@section('heading', 'Import transactions')
@section('content')
<div class="card" style="max-width:850px">
    <div class="eyebrow">Bulk transaction capture</div>
    <h2 class="section-heading" style="margin-top:8px">Import income and expenses from CSV</h2>
    <p class="muted" style="font-size:13px;line-height:1.7">Upload a spreadsheet exported from your bank, M-Pesa statement or personal records. We validate every row and show you a preview before anything is added to your ledger.</p>
    <form method="POST" action="{{ route('imports.preview.store') }}" enctype="multipart/form-data" style="margin-top:22px">
        @csrf
        <div class="field" style="max-width:520px;margin-bottom:16px">
            <label for="file">CSV file (maximum 2 MB and 500 transactions)</label>
            <input id="file" name="file" type="file" accept=".csv,text/csv" required>
        </div>
        <button class="btn" type="submit">Validate and preview CSV</button>
    </form>
</div>
<div class="card" style="margin-top:18px">
    <h2 class="section-heading">Required CSV format</h2>
    <p class="muted" style="font-size:12px">Use these column names in the first row. Dates must use YYYY-MM-DD. Amounts must be positive KES values with up to two decimal places.</p>
    <div class="table-wrap"><table><thead><tr><th>type</th><th>date</th><th>description</th><th>amount</th><th>category</th><th>tag</th></tr></thead><tbody><tr><td>income</td><td>2026-11-01</td><td>Part-time work</td><td>15000.00</td><td></td><td>Work</td></tr><tr><td>expense</td><td>2026-11-02</td><td>Groceries</td><td>1250.50</td><td>Food</td><td>Home</td></tr></tbody></table></div>
    <p class="muted" style="font-size:11px;margin-top:12px">Required columns: type, date, description, amount. Optional columns: category, tag. Supported transaction types: income and expense. All imported amounts are recorded in KES. Duplicate-looking rows are not automatically removed; review your CSV before confirming.</p>
</div>
@endsection
