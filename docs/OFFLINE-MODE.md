# Offline mode

## Supported behavior

- The app can be installed on browsers that support web app manifests and service workers.
- After the first successful visit, the service worker keeps only the offline fallback page, manifest, icon, retry policy and offline queue scripts in its static cache.
- Authenticated pages, financial balances, reports, API responses, credentials and cross-origin resources are never cached by the service worker.
- Income and expense creation forms can be queued in IndexedDB while offline. Each form uses its existing per-user UUID submission token; the database unique constraint makes server retries idempotent.
- When the user returns online while signed in, the page sends queued records to the normal authenticated endpoints. The server remains authoritative for validation, wallet ownership, currency conversion and persistence.
- Temporary network failures, HTTP 408/429 and HTTP 5xx responses use exponential backoff starting at 5 seconds, capped at 5 minutes, with at most 8 automatic attempts per item. The next retry is scheduled while an authenticated page remains open; pending items remain stored if the browser closes.
- HTTP validation/conflict errors and expired sessions are not retried automatically. The user can edit the item or refresh the session, then select Sync now for a manual retry. Items are never silently discarded.

## Important limitations

- Offline creation currently supports income and expense records only. Editing/deleting records, transfers, savings, budgets, debt repayments, recurring schedules and reports require a connection.
- Synchronisation runs when an authenticated app page is open and connectivity returns, or when the user selects Sync now. It is not guaranteed background sync while the browser is closed.
- Balances and reports remain stale until queued records have synchronised and the page is refreshed.
- Foreign-currency expenses still require a valid server-side exchange-rate quote when synchronised. A rejected item remains in the local outbox; choose KES or resolve the rate issue before retrying.
- The browser must support IndexedDB, service workers, crypto.randomUUID() and timers. Service workers require HTTPS, except on localhost.

## Local data and shared-device safety

IndexedDB is origin-local browser storage and is not encrypted by this application. Pending records contain only the fields needed to submit the transaction and the owning account ID; passwords, session cookies and CSRF tokens are not stored. The outbox is filtered by account ID so one signed-in account cannot submit another account's queued items. However, anyone with access to the same unlocked browser profile may inspect local browser storage. Do not enable offline transaction entry on a shared or public device. Clear browser site data when retiring a device, and do not assume pending data survives browser storage eviction or device loss.

## Verification

Use a staging account and synthetic financial data. Test offline queueing, page reload, browser restart, reconnect, repeated delivery of the same submission token, account switching, session expiry, invalid payloads, local storage denial and service-worker updates and retry/backoff behavior. Never test with real financial exports or production data.
