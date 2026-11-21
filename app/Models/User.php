<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->wallets()->firstOrCreate(
                ['name' => 'Main wallet'],
                ['type' => 'cash', 'currency' => 'KES', 'opening_balance' => 0, 'is_default' => true],
            );
        });
    }

    protected $fillable = [
        'fullname',
        'email',
        'password',
        'points',
        'avatar',
        'theme',
        'dashboard_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'points' => 'integer',
            'dashboard_preferences' => 'array',
        ];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function walletTransfers(): HasMany
    {
        return $this->hasMany(WalletTransfer::class);
    }

    public function income()
    {
        return $this->hasMany(Income::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function savings()
    {
        return $this->hasMany(Saving::class);
    }

    public function savingsGoals()
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    public function recurringTransactions()
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function billReminders()
    {
        return $this->hasMany(BillReminder::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function financialStreak(): HasOne
    {
        return $this->hasOne(UserFinancialStreak::class);
    }

    public function expenseCaptureDrafts(): HasMany
    {
        return $this->hasMany(ExpenseCaptureDraft::class);
    }

    public function expenseCaptureWebhookCredential(): HasOne
    {
        return $this->hasOne(ExpenseCaptureWebhookCredential::class);
    }
}
