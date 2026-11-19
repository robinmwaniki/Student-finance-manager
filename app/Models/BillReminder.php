<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillReminder extends Model
{
    protected $fillable = ['user_id', 'title', 'category', 'amount', 'due_date', 'recurrence', 'due_day', 'due_month', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date', 'due_day' => 'integer', 'due_month' => 'integer', 'lock_version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
