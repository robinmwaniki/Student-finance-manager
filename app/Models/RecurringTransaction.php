<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id', 'category', 'amount', 'type', 'frequency', 'next_due',
        'status', 'label', 'day_of_month', 'active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_due' => 'date',
            'day_of_month' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RecurringLog::class, 'recurring_id');
    }
}
