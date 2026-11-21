@extends('layouts.app')

@section('title', 'Wallets')
@section('heading', 'Wallets & transfers')

@section('content')
<div class="grid stats" style="margin-bottom:18px">
    <div class="card">
        <div class="stat-label">Total wallet balance</div>
        <div class="stat-value">KES {{ number_format($wallets->sum(fn ($wallet) => (float) $wallet->balance), 2) }}</div>
        <div class="stat-note">{{ $wallets->count() }} wallet(s)</div>
    </div>
    <div class="card">
        <div class="stat-label">Cash wallets</div>
        <div class="stat-value">{{ $wallets->where('type', 'cash')->count() }}</div>
        <div class="stat-note">Physical cash accounts</div>
    </div>
    <div class="card">
        <div class="stat-label">Bank accounts</div>
        <div class="stat-value">{{ $wallets->where('type', 'bank')->count() }}</div>
        <div class="stat-note">Linked bank balances tracked manually</div>
    </div>
    <div class="card">
        <div class="stat-label">M-Pesa wallets</div>
        <div class="stat-value">{{ $wallets->where('type', 'mpesa')->count() }}</div>
        <div class="stat-note">Mobile money balances</div>
    </div>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));margin-bottom:18px">
    @forelse($wallets as $wallet)
        <section class="card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <div class="stat-label">{{ strtoupper($wallet->type) }} @if($wallet->is_default) · DEFAULT @endif</div>
                    <h2 class="section-heading" style="margin:7px 0 0">{{ $wallet->name }}</h2>
                </div>
                <span class="muted" style="font-size:11px">{{ $wallet->currency }}</span>
            </div>
            <div class="stat-value" style="margin-top:18px">KES {{ number_format((float) $wallet->balance, 2) }}</div>
            <div class="stat-note">Opening balance: KES {{ number_format((float) $wallet->opening_balance, 2) }}</div>
            <div class="stat-note" style="margin-top:9px">{{ $wallet->income_count }} income · {{ $wallet->expenses_count }} expenses</div>
        </section>
    @empty
        <div class="card"><p class="muted">No wallets found. Create your first wallet below.</p></div>
    @endforelse
</div>

<div class="grid two-col">
    <section class="card">
        <h2 class="section-heading">Create a wallet</h2>
        <form method="POST" action="{{ route('wallets.store') }}">
            @csrf
            <div class="field" style="margin-bottom:12px">
                <label for="wallet-name">Wallet name</label>
                <input id="wallet-name" name="name" maxlength="80" placeholder="e.g. M-Pesa, Equity Bank" value="{{ old('name') }}" required>
            </div>
            <div class="form-grid" style="grid-template-columns:1fr 1fr">
                <div class="field">
                    <label for="wallet-type">Type</label>
                    <select id="wallet-type" name="type" required>
                        <option value="cash">Cash</option>
                        <option value="bank" @selected(old('type') === 'bank')>Bank</option>
                        <option value="mpesa" @selected(old('type') === 'mpesa')>M-Pesa</option>
                        <option value="other" @selected(old('type') === 'other')>Other</option>
                    </select>
                </div>
                <div class="field">
                    <label for="opening-balance">Opening balance (KES)</label>
                    <input id="opening-balance" name="opening_balance" type="number" min="0" max="999999999999.99" step="0.01" value="{{ old('opening_balance', '0.00') }}" required>
                </div>
            </div>
            <p class="muted" style="font-size:11px;margin:12px 0">Opening balance is the amount already held in this wallet when you start tracking it.</p>
            <button class="btn" type="submit">Create wallet</button>
        </form>
    </section>

    <section class="card">
        <h2 class="section-heading">Transfer between wallets</h2>
        @if($wallets->count() < 2)
            <p class="muted" style="font-size:12px">Create at least two wallets before making a transfer.</p>
        @else
            <form method="POST" action="{{ route('wallets.transfers.store') }}">
                @csrf
                <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                <div class="field" style="margin-bottom:12px">
                    <label for="from-wallet">From wallet</label>
                    <select id="from-wallet" name="from_wallet_id" required>
                        @foreach($wallets as $wallet)
                            <option value="{{ $wallet->id }}" @selected(old('from_wallet_id') == $wallet->id)>{{ $wallet->name }} · KES {{ number_format((float) $wallet->balance, 2) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:12px">
                    <label for="to-wallet">To wallet</label>
                    <select id="to-wallet" name="to_wallet_id" required>
                        @foreach($wallets as $wallet)
                            <option value="{{ $wallet->id }}" @selected(old('to_wallet_id') == $wallet->id)>{{ $wallet->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr">
                    <div class="field">
                        <label for="transfer-amount">Amount (KES)</label>
                        <input id="transfer-amount" name="amount" type="number" min="0.01" max="999999999999.99" step="0.01" value="{{ old('amount') }}" required>
                    </div>
                    <div class="field">
                        <label for="transfer-date">Date</label>
                        <input id="transfer-date" name="transfer_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                    </div>
                </div>
                <div class="field" style="margin:12px 0">
                    <label for="transfer-note">Note (optional)</label>
                    <input id="transfer-note" name="note" maxlength="255" value="{{ old('note') }}" placeholder="e.g. Move funds to M-Pesa">
                </div>
                <p class="muted" style="font-size:11px;margin:12px 0">Transfers move money between wallets. They are not recorded as income or expenses, so totals are not double-counted.</p>
                <button class="btn" type="submit">Transfer funds</button>
            </form>
        @endif
    </section>
</div>

<section class="card" style="margin-top:18px">
    <h2 class="section-heading">Recent transfers</h2>
    @if($transfers->isEmpty())
        <p class="muted" style="font-size:12px">No transfers have been made yet.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>From</th><th>To</th><th>Note</th><th style="text-align:right">Amount</th></tr></thead>
                <tbody>
                @foreach($transfers as $transfer)
                    <tr>
                        <td>{{ $transfer->transfer_date->format('d M Y') }}</td>
                        <td>{{ $transfer->fromWallet->name }}</td>
                        <td>{{ $transfer->toWallet->name }}</td>
                        <td>{{ $transfer->note ?: '—' }}</td>
                        <td class="amount" style="text-align:right">KES {{ number_format((float) $transfer->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
