# Browser-local QR Code Generator

The authenticated **Tools → QR code generator** page creates a QR image in the user's browser. It does not submit the QR payload to a Laravel endpoint and does not store QR history.

## Supported content

- **Plain text:** short messages, event details and public references.
- **Public website link:** HTTP/HTTPS URLs only. URLs containing embedded credentials or sensitive query parameters (password, PIN, token, session, API key, authorization, account/card number, CVV/CVC) are rejected.
- **M-Pesa payment reference:** Paybill or Till number, account/reference and an optional KES amount. The output is a human-readable payment reference only. It does not initiate, authorize or verify an M-Pesa transaction.

## Validation and safety

- Payloads must contain 1–1,000 UTF-8 bytes.
- Empty content, control characters, obvious credential fields and private-key headers are rejected.
- A separate user confirmation is required before generation, acknowledging that QR contents can be read by anyone who scans the image and must not contain passwords, PINs, one-time codes, private keys, session tokens or confidential account details.
- The payment reference builder accepts 5–10 digit merchant codes, a 1–40 character reference using a restricted character set, and an optional positive amount with up to two decimal places.
- Error correction can be set to Medium, Quartile or High.
- The preview is generated locally and can be downloaded as a GIF image.

## Privacy and third-party library

The page loads the pinned `qrcode-generator` 2.0.4 UTF-8 browser library from jsDelivr. The CDN request retrieves the library only; the user's content is not included in that request or sent to the CDN, the Laravel application, or a QR image service. If the CDN is unavailable, generation is disabled and the page displays an error; the payload remains in the browser.

The QR engine is based on the MIT-licensed [qrcode-generator project](https://github.com/kazuhikoarase/qrcode-generator). The app does not retain QR payloads, generated images or generation history.

## Tests

- Node's built-in test runner validates content length, UTF-8 byte accounting, privacy confirmation, control characters, credential patterns, URL protocols/credentials/sensitive query keys, payment reference fields and unsupported types.
- Laravel feature tests verify authentication, tool navigation, the client-side generation UI and the absence of a server-side payload submission endpoint.
