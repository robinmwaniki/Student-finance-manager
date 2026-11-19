# Financial calculators and currency tools

## Loan and interest calculator

The loan calculator supports monthly, weekly and biweekly repayment periods, a principal, annual nominal interest rate and term from 1 to 360 months. It returns a rounded amortisation schedule with payment, principal, interest and remaining balance for each period. Balances and payments are rounded to cents. The final payment is adjusted to clear any remaining cent balance.

The estimate excludes lender fees, insurance, penalties, variable rates and lender-specific day-count conventions. It is an educational estimate, not a loan offer or financial recommendation.

## Bill splitting

The split calculator supports 1 to 100 participants and a 0–100% tip. The tip is calculated on the subtotal. The final amount is converted to integer cents and remainder cents are distributed one at a time to the first participants, ensuring the shares add up exactly to the total.

## Currency conversion and expense metadata

- Exchange rates are fetched from the public open.er-api.com endpoint. No API key or Composer package is required.
- Fresh public rates are cached for one hour. The last known rate is retained for up to seven days as a stale fallback.
- Every conversion shows the provider timestamp and source. If only a stale rate is available, the user must explicitly accept it before converting or saving a foreign-currency expense.
- If no rate is available, foreign-currency conversion is rejected gracefully; users can still record a KES expense.
- The supported list includes KES, USD, EUR, GBP, TZS, UGX, ZAR, AED, INR, CAD, AUD, JPY, CHF, CNY and NGN.
- The expense ledger's amount remains the KES-normalized value, so existing dashboards, budgets and reports continue to sum consistently in KES.
- Original amount, original currency, exchange rate and converted amount are stored as metadata. Editing an expense through the existing KES editor resets its metadata to KES at a 1:1 rate. Historical rows with null currency metadata are treated as KES.

Rates are indicative reference rates and may differ from bank, card-network, M-Pesa or cash-exchange rates. Confirm the actual rate and fees with the provider before making a transaction.
