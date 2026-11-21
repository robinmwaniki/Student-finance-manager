<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'currency', 'opening_balance', 'is_default'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function income(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(WalletTransfer::class, 'from_wallet_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(WalletTransfer::class, 'to_wallet_id');
    }

    public function balanceInCents(): int
    {
        $income = self::toCents((string) $this->income()->sum('amount'));
        $expenses = self::toCents((string) $this->expenses()->sum('amount'));
        $sent = self::toCents((string) $this->outgoingTransfers()->sum('amount'));
        $received = self::toCents((string) $this->incomingTransfers()->sum('amount'));

        return self::toCents((string) $this->opening_balance) + $income - $expenses - $sent + $received;
    }

    public function getBalanceAttribute(): string
    {
        $cents = $this->balanceInCents();
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function toCents(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }
}
