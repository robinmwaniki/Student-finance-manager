@extends('layouts.app')
@section('title', 'Financial tools')
@section('heading', 'Financial tools')
@section('content')
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
    <section class="card">
        <h2 class="section-heading">Loan and interest calculator</h2>
        <p class="muted" style="font-size:11px;line-height:1.6">Estimate repayments and see a period-by-period amortisation schedule. This is an estimate, not a lender quote; fees and lender-specific terms are excluded.</p>
        <form method="POST" action="{{ route('tools.loan') }}" class="form-grid">@csrf
            <div class="field"><label>Principal (KES)</label><input name="principal" type="number" min="0.01" step="0.01" value="{{ old('principal', 100000) }}" required></div>
            <div class="field"><label>Annual interest rate (%)</label><input name="annual_rate" type="number" min="0" max="100" step="0.01" value="{{ old('annual_rate', 12) }}" required></div>
            <div class="field"><label>Term (months)</label><input name="term_months" type="number" min="1" max="360" value="{{ old('term_months', 12) }}" required></div>
            <div class="field"><label>Repayment frequency</label><select name="frequency"><option value="monthly" @selected(old('frequency','monthly') === 'monthly')>Monthly</option><option value="weekly" @selected(old('frequency') === 'weekly')>Weekly</option><option value="biweekly" @selected(old('frequency') === 'biweekly')>Every two weeks</option></select></div>
            <div><button class="btn" type="submit">Calculate repayment</button></div>
        </form>
        @if(session('loan_result'))
            @php($loan = session('loan_result'))
            <div style="border-top:1px solid #e8edf4;margin-top:16px;padding-top:14px">
                <div class="eyebrow">Estimated result</div>
                <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:10px 0">
                    <div><span class="muted" style="font-size:10px">Periodic payment</span><strong style="display:block">KES {{ number_format($loan['periodic_payment'],2) }}</strong></div>
                    <div><span class="muted" style="font-size:10px">Total interest</span><strong style="display:block">KES {{ number_format($loan['total_interest'],2) }}</strong></div>
                    <div><span class="muted" style="font-size:10px">Total paid</span><strong style="display:block">KES {{ number_format($loan['total_paid'],2) }}</strong></div>
                    <div><span class="muted" style="font-size:10px">Payments</span><strong style="display:block">{{ $loan['periods'] }}</strong></div>
                </div>
                <div class="table-wrap" style="max-height:260px;overflow:auto"><table><thead><tr><th>#</th><th>Due</th><th>Payment</th><th>Interest</th><th>Balance</th></tr></thead><tbody>
                @foreach($loan['schedule'] as $row)<tr><td>{{ $row['period'] }}</td><td>{{ $row['due_date'] }}</td><td>{{ number_format($row['payment'],2) }}</td><td>{{ number_format($row['interest'],2) }}</td><td>{{ number_format($row['balance'],2) }}</td></tr>@endforeach
                </tbody></table></div>
            </div>
        @endif
    </section>

    <section class="card">
        <h2 class="section-heading">Split a bill</h2>
        <p class="muted" style="font-size:11px;line-height:1.6">Tip is calculated on the subtotal. Remainder cents are assigned one at a time to the first participants so the shares add up exactly.</p>
        <form method="POST" action="{{ route('tools.split') }}" class="form-grid">@csrf
            <div class="field"><label>Bill subtotal (KES)</label><input name="subtotal" type="number" min="0.01" step="0.01" value="{{ old('subtotal', 1000) }}" required></div>
            <div class="field"><label>Participants</label><input name="participants" type="number" min="1" max="100" value="{{ old('participants', 3) }}" required></div>
            <div class="field"><label>Tip (%)</label><input name="tip_percent" type="number" min="0" max="100" step="0.01" value="{{ old('tip_percent', 0) }}" required></div>
            <div><button class="btn" type="submit">Split bill</button></div>
        </form>
        @if(session('split_result'))
            @php($split = session('split_result'))
            <div style="border-top:1px solid #e8edf4;margin-top:16px;padding-top:14px">
                <div class="eyebrow">Each person pays</div>
                <div class="stat-value">KES {{ number_format($split['total'],2) }} total</div>
                <p class="muted" style="font-size:11px">Subtotal KES {{ number_format($split['subtotal'],2) }} · Tip KES {{ number_format($split['tip'],2) }}</p>
                <div class="table-wrap"><table><thead><tr><th>Participant</th><th>Share</th></tr></thead><tbody>@foreach($split['shares'] as $index => $share)<tr><td>Person {{ $index + 1 }}</td><td>KES {{ number_format($share,2) }}</td></tr>@endforeach</tbody></table></div>
            </div>
        @endif
    </section>

    <section class="card">
        <h2 class="section-heading">Currency converter</h2>
        <p class="muted" style="font-size:11px;line-height:1.6">Rates are indicative public reference rates, not bank, card-network or M-Pesa settlement quotes. Every result includes its provider timestamp and source.</p>
        <form method="POST" action="{{ route('tools.currency') }}" class="form-grid">@csrf
            <div class="field"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', 100) }}" required></div>
            <div class="field"><label>From</label><select name="from">@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(old('from','USD') === $currency)>{{ $currency }}</option>@endforeach</select></div>
            <div class="field"><label>To</label><select name="to">@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(old('to','KES') === $currency)>{{ $currency }}</option>@endforeach</select></div>
            @if(session('stale_quote'))
                <label style="display:flex;gap:8px;align-items:flex-start;font-size:11px;color:#9a5b0a"><input type="checkbox" name="accept_stale_rate" value="1"><span>Accept the cached rate shown below even though it may be outdated.</span></label>
                <p class="muted" style="font-size:10px">Cached rate: 1 {{ session('stale_quote.from') }} = {{ number_format(session('stale_quote.rate'),6) }} {{ session('stale_quote.to') }} · {{ session('stale_quote.updated_at') }}</p>
            @endif
            <div><button class="btn" type="submit">Convert</button></div>
        </form>
        @if(session('currency_result'))
            @php($conversion = session('currency_result'))
            <div style="border-top:1px solid #e8edf4;margin-top:16px;padding-top:14px">
                <div class="eyebrow">Converted amount</div>
                <div class="stat-value">{{ $conversion['to'] }} {{ number_format($conversion['converted'],2) }}</div>
                <p class="muted" style="font-size:10px;line-height:1.7">Rate: 1 {{ $conversion['from'] }} = {{ number_format($conversion['rate'],8) }} {{ $conversion['to'] }}<br>Updated: {{ $conversion['updated_at'] }}<br>Source: {{ $conversion['source'] }}</p>
                @if($conversion['stale'])<p style="color:#9a5b0a;font-size:10px">Warning: this result uses a cached stale rate.</p>@endif
            </div>
        @endif
    </section>

    <section class="card">
        <h2 class="section-heading">QR code generator</h2>
        <p class="muted" style="font-size:11px;line-height:1.6">Create a shareable QR image for safe text, public web links or M-Pesa payment references. Generation runs in your browser; content is not sent to a QR service.</p>
        <a class="btn" href="{{ route('tools.qr') }}">Open QR code generator</a>
    </section>
</div>
@endsection
