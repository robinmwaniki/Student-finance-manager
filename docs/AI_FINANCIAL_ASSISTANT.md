# AI Financial Assistant

## Overview

The authenticated Financial Assistant page provides a conversational financial-literacy helper and deterministic personalised tips. AI chat is optional: finance entry, reports, budgets and other core workflows do not depend on a provider.

## Provider configuration

Configure these server-side environment variables in the deployment secret manager. Never place provider credentials in Blade, JavaScript, public configuration or source control.

| Variable | Purpose | Default |
|---|---|---|
| `AI_PROVIDER` | `gemini` or `groq`; use `none` to disable | `none` |
| `AI_API_KEY` | Server-side provider key | unset |
| `AI_MODEL` | Provider model identifier | `gemini-2.5-flash` |
| `AI_BASE_URL` | Optional provider-compatible endpoint | Provider default |
| `AI_TIMEOUT` | Total request timeout in seconds | 8 |
| `AI_CONNECT_TIMEOUT` | Connection timeout in seconds | 3 |

For Groq, configure an available Groq model. For a compatible OpenAI chat-completions endpoint, set `AI_BASE_URL` to its chat-completions URL. For Gemini, the default endpoint uses the configured model and sends the API key in the server-side `x-goog-api-key` header.

After changing deployment environment variables, refresh Laravel's configuration cache using the normal deployment process. Do not commit real API keys.

## Privacy and consent

- A user must explicitly tick the consent checkbox for each request before financial aggregates are included.
- Without consent, the provider receives only the user's question and the system safety instruction.
- With consent, only bounded 30-day totals and a current-month budget aggregate are sent (income, expenses, savings, net after savings and budget limits). Individual transactions, categories, labels, names, email addresses, account identifiers and user IDs are not sent.
- Questions and responses are not persisted as conversation history by this feature. Provider credentials remain server-side and are never returned to the browser.
- The configured provider is an external processor. Publish the applicable privacy notice before enabling external AI requests.
- Do not enter passwords, PINs, one-time passwords, reset tokens, session values or sensitive identifiers into chat.

## Local tips and safety

Personalised tips are calculated locally from the authenticated user's own records. They remain available if the provider is missing, unavailable or times out. AI output is informational only and is not regulated financial, investment, tax, legal or professional advice.

The chat endpoint is authenticated and limited to five requests per user per minute. Input and output sizes are bounded, provider connection and total request timeouts are configured, and user prompts are treated as untrusted input. Provider exceptions are logged by type only; prompts, financial summaries and credentials are not logged.

## Operations

No queue, scheduler or third-party package is required. The feature is available at `/assistant`. Configure a provider key only in the deployment environment or secret manager. Core finance workflows remain available when AI is disabled.
