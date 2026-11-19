<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseCaptureDraft extends Model
{
    protected $fillable = [
        'user_id', 'source', 'merchant', 'category', 'amount', 'transaction_date',
        'tag', 'source_reference', 'fingerprint', 'status', 'submission_token',
        'expense_id', 'expires_at', 'lock_version',
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

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
