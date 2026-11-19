# Expense capture and import tools

## Draft-first rule

Every capture method creates a reviewable draft. No SMS, receipt, voice transcript or barcode result is automatically written to the expense ledger. A user must review and submit the category, positive amount and transaction date. Confirmation writes through the existing user-owned expenses relationship and a stable submission token; the draft is then marked confirmed. Drafts expire after 24 hours.

## Capture methods

- **M-Pesa SMS:** Supports outgoing confirmation patterns such as sent-to, paid-to, buy-goods, paybill and till transactions. Incoming transfers and unsupported messages are rejected. The raw SMS is parsed in memory and is not stored; only candidate fields and a one-way fingerprint are retained.
- **Receipt OCR:** Uses the configured Gemini model when AI_PROVIDER=gemini and AI_API_KEY is configured. The user must explicitly consent to sending the selected image to the provider. Images are read from the temporary upload and are not saved to application storage. OCR output is a draft and must be verified.
- **Voice:** Uses browser SpeechRecognition when supported and permitted. The transcript is posted for draft parsing; raw audio is not uploaded or retained. If the browser does not support speech recognition or microphone permission is denied, the user can type the transcript or use the standard expense form.
- **Barcode:** Looks up product names through the public Open Food Facts API. The catalog does not provide a trusted purchase price, so amount and date remain for the user to enter and confirm.
- **Webhook:** An optional per-user webhook credential can be generated from the capture page. The raw bearer token and signing secret are shown once. Store them in the trusted sender's secret manager.

## Signed webhook protocol

POST JSON to /api/expense-capture/mpesa-webhook with:
- Authorization: Bearer token
- X-Webhook-Timestamp: Unix timestamp in seconds (maximum five-minute clock skew)
- X-Webhook-Signature: lowercase hexadecimal HMAC-SHA256 of timestamp + dot + rawBody, using the signing secret
- JSON body: event_id (unique provider event ID) and sms (M-Pesa confirmation text)

The token identifies the user's credential. The HMAC authenticates the exact raw body and timestamp. On rotation, the previous token and secret remain valid for 24 hours to allow sender updates. Each user/event_id pair is accepted once; replays return a duplicate response. Raw webhook payloads are not stored, only an event ID and payload hash.

## Data retention and operations

- Drafts are user-scoped and expire after 24 hours. A user may discard a pending draft.
- No receipt image or raw audio is persisted by this feature.
- Webhook events retain only event IDs, SHA-256 payload hashes and receipt timestamps for replay protection.
- OCR requires server-side Gemini credentials. Barcode lookup requires outbound HTTPS access to Open Food Facts. Speech recognition requires a supported browser and microphone permission.
- All capture features have manual expense entry as a fallback.
