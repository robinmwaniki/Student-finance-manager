<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Saving extends Model
{
    protected $table = 'savings';

    protected $fillable = ['user_id', 'goal', 'amount', 'transaction_date', 'savings_month'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
