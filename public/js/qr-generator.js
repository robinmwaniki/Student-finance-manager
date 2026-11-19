(() => {
    'use strict';

    const form = document.getElementById('qr-generator-form');
    if (!form) return;

    const typeSelect = document.getElementById('qr-content-type');
    const safeCheck = document.getElementById('qr-safe-confirmation');
    const levelSelect = document.getElementById('qr-error-level');
    const status = document.getElementById('qr-status');
    const preview = document.getElementById('qr-preview');
    const previewPanel = document.getElementById('qr-preview-panel');
    const download = document.getElementById('qr-download');
    const byteCount = document.getElementById('qr-byte-count');
    const generateButton = document.getElementById('qr-generate');

    function message(text, isError = false) {
        status.textContent = text;
        status.dataset.state = isError ? 'error' : 'success';
    }

    function hidePreview() {
        previewPanel.hidden = true;
        preview.removeAttribute('src');
        download.removeAttribute('href');
        download.hidden = true;
        byteCount.textContent = '';
    }

    function selectedFields() {
        return {
            text: document.getElementById('qr-text').value,
            url: document.getElementById('qr-url').value,
            method: document.getElementById('qr-payment-method').value,
            code: document.getElementById('qr-payment-code').value,
            reference: document.getElementById('qr-payment-reference').value,
            amount: document.getElementById('qr-payment-amount').value,
        };
    }

    function updateContentPanels() {
        const selected = typeSelect.value;
        document.querySelectorAll('[data-qr-panel]').forEach(panel => {
            panel.hidden = panel.dataset.qrPanel !== selected;
        });
        hidePreview();
        message('Choose your content, confirm it is safe to share, then generate the QR code.');
    }

    typeSelect.addEventListener('change', updateContentPanels);

    form.addEventListener('submit', event => {
        event.preventDefault();
        hidePreview();

        if (!window.QrPayload || typeof window.qrcode !== 'function') {
            message('The QR engine could not be loaded. Refresh the page when your connection is available. Your payload has not been uploaded.', true);
            return;
        }

        const result = window.QrPayload.buildPayload(typeSelect.value, selectedFields(), safeCheck.checked);
        if (!result.valid) {
            message(result.error, true);
            return;
        }

        try {
            const qr = window.qrcode(0, levelSelect.value);
            qr.addData(result.payload);
            qr.make();

            const dataUrl = qr.createDataURL(5, 4);
            if (!dataUrl.startsWith('data:image/')) {
                throw new Error('The QR engine returned an unsupported image format.');
            }

            preview.src = dataUrl;
            preview.alt = 'Generated QR code preview';
            previewPanel.hidden = false;
            download.href = dataUrl;
            download.download = 'student-finance-qr-code.gif';
            download.hidden = false;
            byteCount.textContent = result.bytes + ' / ' + window.QrPayload.MAX_BYTES + ' UTF-8 bytes · Error correction ' + levelSelect.value;
            message('QR code generated in this browser. The payload was not sent to the application server or a QR provider.');
        } catch (error) {
            message(error && error.message ? error.message : 'This content could not be encoded. Shorten it and try again.', true);
        }
    });

    form.addEventListener('reset', () => {
        window.setTimeout(() => {
            updateContentPanels();
            safeCheck.checked = false;
        }, 0);
    });

    updateContentPanels();
})();
