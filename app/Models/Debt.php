<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtRepayment extends Model
{
    protected $fillable = ['debt_id', 'user_id', 'amount', 'repayment_date', 'notes', 'submission_token'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'repayment_date' => 'date'];
    }

    public function debt(): BelongsTo { return $this->belongsTo(Debt::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
