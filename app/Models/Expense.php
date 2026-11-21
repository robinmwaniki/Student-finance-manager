<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['user_id', 'wallet_id', 'category', 'amount', 'transaction_date', 'tag', 'submission_token', 'original_amount', 'original_currency', 'exchange_rate', 'converted_amount', 'exchange_rate_updated_at', 'exchange_rate_source'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date', 'original_amount' => 'decimal:2', 'exchange_rate' => 'decimal:10', 'converted_amount' => 'decimal:2', 'exchange_rate_updated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if ($expense->wallet_id === null) {
                $expense->wallet_id = Wallet::query()->firstOrCreate(
                    ['user_id' => $expense->user_id, 'name' => 'Main wallet'],
                    ['type' => 'cash', 'currency' => 'KES', 'opening_balance' => 0, 'is_default' => true],
                )->id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
