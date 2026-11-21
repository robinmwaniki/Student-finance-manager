# Multi-wallet management and transfers

## Wallets

Each account receives a default **Main wallet** denominated in KES. Users can create additional wallets for cash, bank accounts, M-Pesa and other KES balances. Wallet names are unique per account.

A wallet stores its opening balance. Its current balance is derived from its opening balance and the transactions assigned to it:

`opening balance + income - expenses - outgoing transfers + incoming transfers`

Historical income and expense records are assigned to the account's Main wallet when this migration runs. New income and expense records are assigned to the selected wallet; older creation paths that do not specify a wallet fall back to Main wallet.

## Transfers

A transfer moves money between two wallets owned by the same user. Transfers:
- Must use two different wallets with the same currency.
- Must have a positive amount with no more than two decimal places.
- Cannot exceed the available balance in the source wallet.
- Are stored as one transfer record, not as income plus expense.
- Are idempotent per user and submission token.
- Lock the source and destination wallet rows in ascending ID order while checking and recording the transfer.

This keeps transfers out of income and expense totals and prevents the same movement of money from being counted twice.

## Scope and limitations

- Wallet currency is KES in this first iteration. Existing foreign-currency expenses continue to be converted and stored in KES.
- Wallet balances are calculated from the ledger records; there is no separate mutable balance column to drift out of sync.
- Opening balances are entered by the user when creating a wallet.
- Wallet deletion and cross-currency transfers are intentionally not offered.
- M-Pesa/bank synchronization is not implied: wallet balances reflect records entered or imported into the application.

## Verification

The feature tests cover default-wallet creation, assigning income and expenses to a selected wallet, successful transfers, insufficient funds and cross-account transfer rejection.
