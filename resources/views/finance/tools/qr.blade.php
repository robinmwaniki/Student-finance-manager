@extends('layouts.app')
@section('title', 'QR code generator')
@section('heading', 'QR code generator')
@section('content')
<style>
.qr-layout{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(260px,.85fr);gap:16px;align-items:start}.qr-card h2{font:600 16px 'Space Grotesk',sans-serif;margin:0 0 8px}.qr-help{font-size:11px;color:#8490a3;line-height:1.6;margin:0 0 15px}.qr-field{margin-bottom:13px}.qr-field label{display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:7px}.qr-field input,.qr-field select,.qr-field textarea{width:100%;border:1px solid #dfe5ee;border-radius:8px;padding:10px 11px;font:13px 'DM Sans',sans-serif;color:#172033;background:#fff}.qr-field input,.qr-field select{height:42px}.qr-field textarea{min-height:130px;resize:vertical;line-height:1.5}.qr-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.qr-confirm{display:flex;gap:9px;align-items:flex-start;padding:12px;border:1px solid #e5eaf1;border-radius:9px;font-size:11px;line-height:1.5;color:#52617a}.qr-confirm input{margin-top:2px}.qr-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:15px}.qr-status{margin-top:13px;font-size:12px;line-height:1.5}.qr-status[data-state="error"]{color:#b42318}.qr-status[data-state="success"]{color:#16835b}.qr-preview{min-height:300px;display:grid;place-items:center;border:1px dashed #d8e0ea;border-radius:12px;background:#f8fafc;padding:20px}.qr-preview img{width:min(100%,270px);height:auto;image-rendering:pixelated}.qr-preview[hidden]{display:none}.qr-preview-placeholder{text-align:center;color:#8793a6;font-size:12px;line-height:1.6}.qr-note{padding:12px;border-radius:9px;background:#f6f8fb;color:#64748b;font-size:10px;line-height:1.7;margin-top:14px}.qr-small{font-size:10px;color:#8793a6;margin-top:10px}
@media(max-width:760px){.qr-layout{grid-template-columns:1fr}.qr-row{grid-template-columns:1fr}}
body[data-theme="dark"] .qr-field label{color:#a9b6ca}
body[data-theme="dark"] .qr-field input,body[data-theme="dark"] .qr-field select,body[data-theme="dark"] .qr-field textarea{background:#0f1a2b;color:#e5eaf3;border-color:#34465f}
body[data-theme="dark"] .qr-confirm{border-color:#34465f;color:#c5d0df}
body[data-theme="dark"] .qr-preview{background:#0f1a2b;border-color:#34465f}
body[data-theme="dark"] .qr-note{background:#1c2b42;color:#c5d0df}
</style>

<div class="qr-layout">
    <section class="card qr-card">
        <h2>Choose what to encode</h2>
        <p class="qr-help">QR generation happens in this browser. Choose a content type, enter the information and confirm it is safe to share.</p>

        <form id="qr-generator-form" autocomplete="off">
            <div class="qr-field">
                <label for="qr-content-type">Content type</label>
                <select id="qr-content-type">
                    <option value="text">Plain text</option>
                    <option value="url">Public website link</option>
                    <option value="payment">M-Pesa payment reference</option>
                </select>
            </div>

            <div data-qr-panel="text">
                <div class="qr-field">
                    <label for="qr-text">Text to encode</label>
                    <textarea id="qr-text" maxlength="4000" placeholder="Enter a short message, event details or public reference…"></textarea>
                </div>
            </div>

            <div data-qr-panel="url" hidden>
                <div class="qr-field">
                    <label for="qr-url">Public web address</label>
                    <input id="qr-url" type="url" maxlength="1000" placeholder="https://example.com">
                    <p class="qr-small">Only HTTP and HTTPS links without embedded credentials or sensitive token parameters are accepted.</p>
                </div>
            </div>

            <div data-qr-panel="payment" hidden>
                <div class="qr-row">
                    <div class="qr-field"><label for="qr-payment-method">Payment reference type</label><select id="qr-payment-method"><option value="paybill">Paybill</option><option value="till">Buy Goods / Till</option></select></div>
                    <div class="qr-field"><label for="qr-payment-code">Paybill / Till number</label><input id="qr-payment-code" inputmode="numeric" maxlength="10" placeholder="e.g. 123456"></div>
                </div>
                <div class="qr-field"><label for="qr-payment-reference">Account / payment reference</label><input id="qr-payment-reference" maxlength="40" placeholder="e.g. INV-2026-0042"></div>
                <div class="qr-field"><label for="qr-payment-amount">Amount (KES, optional)</label><input id="qr-payment-amount" type="number" min="0.01" max="999999999" step="0.01" placeholder="e.g. 250.00"></div>
                <p class="qr-small">This encodes human-readable payment instructions only. It does not initiate, authorize or verify an M-Pesa payment.</p>
            </div>

            <div class="qr-row">
                <div class="qr-field"><label for="qr-error-level">Error correction</label><select id="qr-error-level"><option value="M">Medium (recommended)</option><option value="Q">Quartile</option><option value="H">High</option></select></div>
            </div>

            <label class="qr-confirm">
                <input id="qr-safe-confirmation" type="checkbox">
                <span>I confirm this content contains no passwords, PINs, one-time codes, private keys, session tokens or confidential account/financial information. I understand anyone who scans the QR can read its contents.</span>
            </label>

            <div class="qr-actions">
                <button class="btn" id="qr-generate" type="submit">Generate QR code</button>
                <button class="btn btn-light" type="reset">Clear</button>
            </div>
            <p class="qr-status" id="qr-status" role="status" aria-live="polite">Choose your content, confirm it is safe to share, then generate the QR code.</p>
        </form>
    </section>

    <section class="card qr-card">
        <h2>Preview & download</h2>
        <p class="qr-help">Scan the preview with a camera app to check the encoded content before sharing or printing it.</p>
        <div class="qr-preview" id="qr-preview-panel" hidden>
            <img id="qr-preview" alt="Generated QR code preview">
        </div>
        <div class="qr-preview" id="qr-preview-empty">
            <div class="qr-preview-placeholder">Your QR code will appear here<br>after you generate it.</div>
        </div>
        <div class="qr-actions"><a class="btn" id="qr-download" href="#" download="student-finance-qr-code.gif" hidden>Download QR image (GIF)</a></div>
        <p class="qr-small" id="qr-byte-count"></p>
        <div class="qr-note"><strong>Privacy:</strong> the QR engine is downloaded from a pinned jsDelivr library. The text or payment reference is encoded locally in your browser; it is not submitted to this app, jsDelivr or an external QR service. The app does not save QR history.</div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@2.0.4/dist/qrcode_UTF8.js" defer referrerpolicy="no-referrer"></script>
<script src="{{ asset('js/qr-payload.js') }}" defer></script>
<script src="{{ asset('js/qr-generator.js') }}" defer></script>
@endpush
