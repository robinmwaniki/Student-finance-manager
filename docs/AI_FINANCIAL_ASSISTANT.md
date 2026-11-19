# AI Financial Assistant

## Overview

The authenticated Financial Assistant page provides a conversational financial-literacy helper and locally calculated monthly tips. It is optional: finance entry, reports, budgets and other core workflows do not depend on an AI provider.

## Provider configuration

Configure these server-side environment variables; never place provider credentials in Blade, JavaScript, public configuration or source control:

| Variable | Purpose | Default |
|---|---|---|
| `FINANCIAL_ASSISTANT_PROVIDER` | `gemini` or `groq` | `gemini` |
| `FINANCIAL_ASSISTANT_API_KEY` | Server-side provider key | unset |
| `FINANCIAL_ASSISTANT_MODEL` | Provider model identifier | `gemini-2.5-flash` |
| `FINANCIAL_ASSISTANT_BASE_URL` | Optional compatible API base URL | Provider default |
| `FINANCIAL_ASSISTANT_TIMEOUT` | Total request timeout in seconds | 20 |
| `FINANCIAL_ASSISTANT_CONNECT_TIMEOUT` | Connection timeout in seconds | 5 |
| `FINANCIAL_ASSISTANT_MAX_OUTPUT_CHARS` | Maximum displayed response characters | 5000 |

For Groq, set `FINANCIAL_ASSISTANT_PROVIDER=groq` and configure an available Groq model. A compatible OpenAI chat-completions endpoint can be configured with `FINANCIAL_ASSISTANT_BASE_URL`.

After changing deployment environment variables, refresh Laravel's configuration cache using the normal deployment process. Do not commit real API keys.

## Privacy and consent

- A user must explicitly tick the consent checkbox on each request before current-month aggregates are included.
- Without consent, the provider receives the question and safety instructions only.
- With consent, only bounded totals and ratios (income, expenses, savings, budget, savings rate and budget usage) are sent. Transaction rows, dates, categories, names, account identifiers and user IDs are not sent.
- Questions and responses are not persisted by this feature. Provider credentials are only read server-side and are never returned to the browser.
- The configured provider is an external processor; disclose its use in the deployment privacy notice before enabling chat.
- Do not enter passwords, reset tokens, session values or sensitive identifiers into chat.

## Local tips and safety

Personalised tips are calculated from the authenticated user's own current-month Eloquent relationships. They remain available if the provider is missing, unavailable or times out. AI responses are informational only and are not regulated financial, investment, tax or legal advice.

The chat endpoint is authenticated and rate-limited to five requests per user per minute. Requests have bounded input/output sizes and provider connection/total timeouts. User prompts are treated as untrusted input.

## Operations

No queue, scheduler, migration or third-party package is required. The feature is available at `/assistant`. Configure a provider key only in the deployment environment or secret manager.
