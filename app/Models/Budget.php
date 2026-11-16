<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $fillable = ['user_id', 'category', 'amount', 'limit_amount', 'budget_month', 'date'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'limit_amount' => 'decimal:2', 'date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
