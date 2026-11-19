<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptureDraft extends Model
{
    protected $fillable = [
        'user_id',
        'source',
        'external_reference',
        'merchant',
        'amount',
        'transaction_date',
        'category',
        'idempotency_key',
        'status',
        'confirmed_expense_id',
        'expires_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'expires_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'confirmed_expense_id');
    }
}
