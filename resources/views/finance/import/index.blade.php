@extends('layouts.app')
@section('title', 'Import transactions')
@section('heading', 'Import transactions')
@section('content')
<div class="card" style="max-width:850px">
    <div class="eyebrow">Bulk transaction capture</div>
    <h2 class="section-heading" style="margin-top:8px">Import a bank statement or M-PESA SMS</h2>
    <p class="muted" style="font-size:13px;line-height:1.7">Upload a CSV statement or paste successful M-PESA confirmation messages copied from your phone. Every import is validated and previewed before anything is added to your ledger.</p>
    <form method="POST" action="{{ route('imports.preview.store') }}" enctype="multipart/form-data" style="margin-top:22px">
        @csrf
        <div class="field" style="max-width:520px;margin-bottom:16px">
            <label for="file">Bank or M-PESA CSV statement (maximum 2 MB and 500 transactions)</label>
            <input id="file" name="file" type="file" accept=".csv,text/csv">
        </div>
        <div style="text-align:center;color:#8490a3;font-size:11px;font-weight:700;margin:14px 0">OR PASTE M-PESA CONFIRMATION MESSAGES</div>
        <div class="field" style="margin-bottom:16px">
            <label for="mpesa_sms">M-PESA SMS messages (one complete message per line)</label>
            <textarea id="mpesa_sms" name="mpesa_sms" rows="7" maxlength="100000" style="width:100%;border:1px solid #dfe5ee;border-radius:8px;padding:12px;font:12px 'DM Sans',sans-serif;resize:vertical" placeholder="QHX123ABC4 Confirmed. Ksh1,000.00 sent to ... on 26/11/26 at 10:15 AM. New M-PESA balance is Ksh..."></textarea>
        </div>
        <p class="muted" style="font-size:11px;line-height:1.6">Only paste transaction confirmation messages. Never paste your M-PESA PIN, OTP, bank password, card number or CVV. Messages are processed locally by this app and are not sent to an AI service.</p>
        <button class="btn" type="submit">Validate and preview import</button>
    </form>
</div>
<div class="card" style="margin-top:18px">
    <h2 class="section-heading">CSV format</h2>
    <p class="muted" style="font-size:12px">For bank statement files, map the columns to the format below. Dates use YYYY-MM-DD and amounts are positive KES values.</p>
    <div class="table-wrap"><table><thead><tr><th>type</th><th>date</th><th>description</th><th>amount</th><th>category</th><th>tag</th></tr></thead><tbody><tr><td>income</td><td>2026-11-01</td><td>Salary</td><td>15000.00</td><td></td><td>Work</td></tr><tr><td>expense</td><td>2026-11-02</td><td>Groceries</td><td>1250.50</td><td>Food</td><td>Home</td></tr></tbody></table></div>
    <p class="muted" style="font-size:11px;margin-top:12px">Required columns: type, date, description, amount. Optional columns: category, tag. Imports are limited to 500 rows. SMS support covers common successful received, sent, payment and withdrawal confirmations; unsupported messages are ignored.</p>
</div>
@endsection
