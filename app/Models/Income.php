<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    protected $table = 'income';

    protected $fillable = ['user_id', 'wallet_id', 'amount', 'source', 'transaction_date', 'tag', 'submission_token'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (Income $income): void {
            if ($income->wallet_id === null) {
                $income->wallet_id = Wallet::query()->firstOrCreate(
                    ['user_id' => $income->user_id, 'name' => 'Main wallet'],
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
