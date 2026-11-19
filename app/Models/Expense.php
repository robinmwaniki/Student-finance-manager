<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'amount',
        'original_amount',
        'original_currency',
        'exchange_rate',
        'exchange_rate_source',
        'exchange_rate_at',
        'transaction_date',
        'tag',
        'submission_token',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'exchange_rate_at' => 'datetime',
            'transaction_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
