# Free transaction imports and financial insights

This release uses the application's existing Laravel, PostgreSQL and PWA stack. It does not require an AI key, paid API, open-banking aggregator or external transaction processor.

## Transaction import
- Use **Import CSV** to upload a bank or M-Pesa statement after converting its columns to the application's documented format: `type,date,description,amount,category,tag`.
- Imports are previewed and only persisted after confirmation.
- Amounts are KES, dates are `YYYY-MM-DD`, and the current import limits are 2 MB and 500 rows.
- Do not upload card numbers, CVVs, PINs, passwords, OTPs or unrelated personal information.
- This is a user-initiated statement import, not a live connection to an M-Pesa wallet or bank account. A free official API for personal transaction aggregation is not assumed.

## Local financial insights
The Financial Insights page calculates current-month income, expenses, savings, top expense categories and explainable recommendations from the signed-in user's own records. Recommendations can flag spending above income, rising expenses, budget usage and a possible small savings target.

All recommendations are advisory. They do not modify budgets, create transactions, transfer money or execute payments. Incomplete transaction records can make the results incomplete; users should review imports before confirming.

## Privacy and cost
Calculations run inside the application against the authenticated user's records. No financial data is sent to an AI provider for local insights. Optional AI chat remains separate and may require a provider account.
