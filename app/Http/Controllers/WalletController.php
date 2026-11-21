<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $wallets = $request->user()->wallets()->withCount(['income', 'expenses'])->orderByDesc('is_default')->orderBy('name')->get();
        $transfers = $request->user()->walletTransfers()
            ->with(['fromWallet:id,name', 'toWallet:id,name'])
            ->latest('transfer_date')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('finance.wallets.index', [
            'wallets' => $wallets,
            'transfers' => $transfers,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('wallets', 'name')->where('user_id', $request->user()->id)],
            'type' => ['required', 'in:cash,bank,mpesa,other'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
        ]);

        $request->user()->wallets()->create([
            ...$data,
            'currency' => 'KES',
            'is_default' => false,
        ]);

        return back()->with('success', 'Wallet created successfully.');
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'from_wallet_id' => ['required', 'integer', 'different:to_wallet_id'],
            'to_wallet_id' => ['required', 'integer', 'different:from_wallet_id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($data, $user): void {
            $existing = $user->walletTransfers()->where('submission_token', $data['submission_token'])->first();
            if ($existing) {
                $sameRequest = (int) $existing->from_wallet_id === (int) $data['from_wallet_id']
                    && (int) $existing->to_wallet_id === (int) $data['to_wallet_id']
                    && self::toCents((string) $existing->amount) === self::toCents((string) $data['amount'])
                    && $existing->transfer_date->toDateString() === $data['transfer_date']
                    && $existing->note === ($data['note'] ?? null);

                if (! $sameRequest) {
                    throw ValidationException::withMessages(['submission_token' => 'This transfer form has already been used. Refresh the page and try again.']);
                }

                return;
            }

            // Lock in ascending ID order to avoid deadlocks between concurrent transfers.
            $locked = $user->wallets()
                ->whereIn('id', [$data['from_wallet_id'], $data['to_wallet_id']])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $from = $locked->get((int) $data['from_wallet_id']);
            $to = $locked->get((int) $data['to_wallet_id']);

            if (! $from || ! $to) {
                throw ValidationException::withMessages(['from_wallet_id' => 'Select two wallets belonging to your account.']);
            }

            if ($from->currency !== $to->currency) {
                throw ValidationException::withMessages(['to_wallet_id' => 'Transfers are only supported between wallets using the same currency.']);
            }

            $availableCents = $from->balanceInCents();
            $transferCents = self::toCents((string) $data['amount']);

            if ($transferCents > $availableCents) {
                throw ValidationException::withMessages(['amount' => 'The source wallet does not have enough available balance for this transfer.']);
            }

            $user->walletTransfers()->create([
                'from_wallet_id' => $from->id,
                'to_wallet_id' => $to->id,
                'amount' => $data['amount'],
                'transfer_date' => $data['transfer_date'],
                'note' => $data['note'] ?? null,
                'submission_token' => $data['submission_token'],
            ]);
        });

        return back()->with('success', 'Transfer completed. Both wallet balances have been updated.');
    }

    private static function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
