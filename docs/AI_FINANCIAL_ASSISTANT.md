# AI financial assistant and personalized tips

## Provider configuration

The assistant uses Laravel's HTTP client and supports server-configured Gemini and Groq chat providers. No AI SDK or additional Composer package is required.

Set these values in the server environment:

| Variable | Purpose |
| --- | --- |
| `AI_PROVIDER` | `gemini`, `groq` or `none` |
| `AI_API_KEY` | Provider key, kept only in server environment |
| `AI_MODEL` | Provider model name |
| `AI_BASE_URL` | Optional full endpoint override for compatible providers |

The default provider is `none`, so the feature is disabled until configured. Never put provider credentials in Blade, JavaScript, HTML, source control or a browser request. Clear the configuration cache after changing environment values.

## Privacy and consent

- General questions send only the submitted question and fixed assistant instructions.
- A separate, unchecked consent control must be selected before any financial context is included.
- With consent, the provider receives only the user's own 30-day aggregate KES income, expenses and savings totals, net-after-savings figure, reporting period and current-month budget total.
- No individual transaction rows, categories, source labels, user IDs, names, email addresses, passwords or session data are sent.
- The assistant does not persist chat history. Provider requests and responses are not written to application logs.
- Personalized tips are calculated locally from the authenticated user's own records and do not call an AI provider.

## Reliability and safety

- The assistant endpoint is authenticated and rate-limited to 10 requests per minute.
- Provider connections use a 3-second connect timeout and an 8-second overall timeout.
- Questions are limited to 1,000 characters and answers to 2,500 characters.
- Provider failures return a generic safe message; they do not affect income, expense, savings or other finance workflows.
- System instructions treat user content as untrusted, reject prompt override requests and limit the assistant to financial-literacy information.
- AI output may be inaccurate and is not regulated financial, tax, legal or investment advice. The assistant cannot execute transactions or modify account records.

## Deterministic tips

Local tips compare the user's last 30 days of income, expenses and savings and evaluate current-month category budgets. Thresholds are explanatory prompts, not financial guarantees or personalized investment recommendations.
