<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringLog extends Model
{
    protected $table = 'recurring_log';

    public $timestamps = false;

    protected $fillable = ['recurring_id', 'user_id', 'applied_month', 'period_key', 'income_id', 'expense_id', 'applied_at'];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
