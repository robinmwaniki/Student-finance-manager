<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['user_id', 'category', 'amount', 'transaction_date', 'tag', 'submission_token', 'original_amount', 'original_currency', 'exchange_rate', 'converted_amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date', 'original_amount' => 'decimal:2', 'exchange_rate' => 'decimal:10', 'converted_amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
