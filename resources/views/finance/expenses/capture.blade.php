@extends('layouts.app')
@section('title', 'Expense capture')
@section('heading', 'Capture an expense')
@section('content')
<section class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Capture tools</h2>
    <p class="muted" style="font-size:12px;line-height:1.7">Every import creates a draft only. Nothing enters your expense ledger until you review and confirm the amount, category and date. Raw SMS messages, receipt images and audio are not stored.</p>
</section>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:18px">
    <section class="card">
        <h2 class="section-heading">M-Pesa SMS</h2>
        <form method="POST" action="{{ route('expense-capture.mpesa') }}">@csrf
            <div class="field"><label for="mpesa-message">Paste an outgoing M-Pesa confirmation</label><textarea id="mpesa-message" name="message" rows="5" maxlength="3000" required style="width:100%;border:1px solid #dfe5ee;border-radius:8px;padding:10px">{{ old('message') }}</textarea></div>
            <button class="btn" type="submit">Extract draft</button>
        </form>
        <p class="muted" style="font-size:10px;margin-top:10px">Incoming transfers and unsupported messages are rejected. The original SMS is discarded after parsing.</p>
    </section>

    <section class="card">
        <h2 class="section-heading">Receipt OCR</h2>
        @if($ocrAvailable)
            <form method="POST" action="{{ route('expense-capture.receipt') }}" enctype="multipart/form-data">@csrf
                <div class="field"><label for="receipt">Receipt image (JPG, PNG or WebP, max 5 MB)</label><input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required></div>
                <label style="display:flex;gap:8px;align-items:flex-start;font-size:11px;color:#52617a;margin:12px 0"><input type="checkbox" name="consent" value="1" required><span>I consent to sending this receipt image to the configured Gemini provider for text extraction. The image is not saved by this app.</span></label>
                <button class="btn" type="submit">Read receipt</button>
            </form>
        @else
            <p class="muted" style="font-size:12px">Receipt OCR requires a server-configured Gemini provider. Use the manual expense form until it is enabled.</p>
        @endif
    </section>

    <section class="card">
        <h2 class="section-heading">Voice expense</h2>
        <form method="POST" action="{{ route('expense-capture.voice') }}">@csrf
            <div class="field"><label for="voice-transcript">Speak or type a draft, e.g. “spent 350 shillings on lunch today”</label><textarea id="voice-transcript" name="transcript" rows="4" maxlength="2000" required style="width:100%;border:1px solid #dfe5ee;border-radius:8px;padding:10px">{{ old('transcript') }}</textarea></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-light" type="button" id="start-voice">Start voice capture</button><button class="btn" type="submit">Create draft</button></div>
            <p id="voice-status" class="muted" style="font-size:10px;margin-top:9px">Browser speech recognition requires microphone permission. If unavailable or denied, type the transcript above.</p>
        </form>
    </section>

    <section class="card">
        <h2 class="section-heading">Barcode lookup</h2>
        <form method="POST" action="{{ route('expense-capture.barcode') }}">@csrf
            <div class="field"><label for="barcode">Product barcode (8–14 digits)</label><input id="barcode" name="barcode" inputmode="numeric" pattern="[0-9]{8,14}" maxlength="14" required></div>
            <button class="btn" type="submit">Find product</button>
        </form>
        <p class="muted" style="font-size:10px;margin-top:10px">Uses the public Open Food Facts catalog. Product matches do not include a trusted purchase price; enter the amount during review.</p>
    </section>
</div>

<section class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Optional M-Pesa webhook</h2>
    <p class="muted" style="font-size:11px;line-height:1.7">Generate a per-account bearer token and signing secret for a trusted webhook sender. The secret is shown once. Requests must include a timestamp and HMAC-SHA256 signature; previous credentials remain valid for 24 hours after rotation.</p>
    <form method="POST" action="{{ route('expense-capture.webhook.rotate') }}">@csrf<button class="btn btn-light" type="submit">Generate / rotate webhook credentials</button></form>
    @if(session('webhook_credentials'))
        <div style="margin-top:14px;padding:12px;background:#f5f7fb;border-radius:9px">
            <strong style="font-size:11px">Copy these credentials now. They will not be shown again.</strong>
            <p style="font-size:10px;word-break:break-all">Bearer token: {{ session('webhook_credentials.token') }}</p>
            <p style="font-size:10px;word-break:break-all">Signing secret: {{ session('webhook_credentials.secret') }}</p>
            <p style="font-size:10px">Webhook URL: {{ url('/api/expense-capture/mpesa-webhook') }}</p>
        </div>
    @endif
</section>

<section class="card">
    <h2 class="section-heading">Drafts awaiting your review</h2>
    @forelse($drafts as $draft)
        <article style="border:1px solid #e8edf4;border-radius:11px;padding:14px;margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                <strong style="font-size:12px">{{ strtoupper($draft->source) }} · {{ $draft->merchant ?: 'Unidentified merchant' }}</strong>
                <span class="muted" style="font-size:10px">Expires {{ $draft->expires_at?->format('d M Y H:i') }}</span>
            </div>
            @if($draft->expires_at?->isPast())
                <p class="muted" style="font-size:11px;margin:12px 0">This draft has expired. It cannot be confirmed; discard it and capture the transaction again.</p>
            @else
            <form method="POST" action="{{ route('expense-capture.confirm', $draft) }}" class="form-grid" style="margin-top:12px">
                @csrf
                <input type="hidden" name="lock_version" value="{{ $draft->lock_version }}">
                <div class="field"><label>Category</label><input name="category" maxlength="50" value="{{ old('category', $draft->category ?: 'General') }}" required></div>
                <div class="field"><label>Amount (KES)</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $draft->amount) }}" required></div>
                <div class="field"><label>Transaction date</label><input name="transaction_date" type="date" max="{{ now(config('app.timezone'))->toDateString() }}" value="{{ old('transaction_date', $draft->transaction_date?->format('Y-m-d')) }}" required></div>
                <div class="field"><label>Tag</label><input name="tag" maxlength="50" value="{{ old('tag', $draft->tag) }}"></div>
                <div><button class="btn" type="submit">Confirm and save expense</button></div>
            </form>
            @endif
            @if($draft->source_reference)<p class="muted" style="font-size:10px;margin:8px 0 0">Reference: {{ $draft->source_reference }}</p>@endif
            <form method="POST" action="{{ route('expense-capture.discard', $draft) }}" style="margin-top:8px">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $draft->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Discard draft</button></form>
        </article>
    @empty
        <p class="muted" style="font-size:12px">No pending capture drafts. Captured details will appear here for review.</p>
    @endforelse
</section>
@endsection

@push('scripts')
<script>
(() => {
    const button = document.getElementById('start-voice');
    const transcript = document.getElementById('voice-transcript');
    const status = document.getElementById('voice-status');
    if (!button || !transcript || !status) return;
    button.addEventListener('click', () => {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            status.textContent = 'Speech recognition is not supported in this browser. Type your expense instead.';
            return;
        }
        const recognition = new SpeechRecognition();
        recognition.lang = 'en-KE';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        recognition.onstart = () => { status.textContent = 'Listening. Speak clearly and check the transcript before creating a draft.'; };
        recognition.onresult = event => {
            transcript.value = event.results[0][0].transcript;
            status.textContent = 'Transcript ready. Review and edit it before creating the draft.';
        };
        recognition.onerror = () => { status.textContent = 'Microphone permission or speech recognition failed. Type the expense instead.'; };
        recognition.start();
    });
})();
</script>
@endpush
