<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    protected $fillable = ['user_id', 'description', 'amount', 'type', 'due_date', 'status', 'submission_token'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date', 'lock_version' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function repayments(): HasMany { return $this->hasMany(DebtRepayment::class); }

    public function outstandingBalanceCents(): int
    {
        return max(0, (int) round((float) $this->amount * 100) - (int) round((float) $this->repayments()->sum('amount') * 100));
    }
}
