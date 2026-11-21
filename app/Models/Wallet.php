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

    public function getBalanceAttribute(): string
    {
        $income = $this->income()->sum('amount');
        $expenses = $this->expenses()->sum('amount');
        $sent = $this->outgoingTransfers()->sum('amount');
        $received = $this->incomingTransfers()->sum('amount');

        return number_format((float) $this->opening_balance + $income - $expenses - $sent + $received, 2, '.', '');
    }
}
