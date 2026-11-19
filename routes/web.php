<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BillReminderController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\SavingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::put('/budgets/{budget}', [BudgetController::class, 'update'])->whereNumber('budget')->name('budgets.update');
    Route::delete('/budgets/{budget}', [BudgetController::class, 'destroy'])->whereNumber('budget')->name('budgets.destroy');
    Route::post('/budgets/copy', [BudgetController::class, 'copy'])->middleware('throttle:10,1')->name('budgets.copy');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/reminders', [BillReminderController::class, 'index'])->name('reminders.index');
    Route::post('/reminders', [BillReminderController::class, 'store'])->name('reminders.store');
    Route::put('/reminders/{reminder}', [BillReminderController::class, 'update'])->whereNumber('reminder')->name('reminders.update');
    Route::patch('/reminders/{reminder}/toggle', [BillReminderController::class, 'toggle'])->whereNumber('reminder')->name('reminders.toggle');
    Route::delete('/reminders/{reminder}', [BillReminderController::class, 'destroy'])->whereNumber('reminder')->name('reminders.destroy');
    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
    Route::put('/journal/{entry}', [JournalController::class, 'update'])->whereNumber('entry')->name('journal.update');
    Route::delete('/journal/{entry}', [JournalController::class, 'destroy'])->whereNumber('entry')->name('journal.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification')->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/income', [IncomeController::class, 'index'])->name('income.index');
    Route::post('/income', [IncomeController::class, 'store'])->name('income.store');
    Route::put('/income/{income}', [IncomeController::class, 'update'])->whereNumber('income')->name('income.update');
    Route::delete('/income/{income}', [IncomeController::class, 'destroy'])->whereNumber('income')->name('income.destroy');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->whereNumber('expense')->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->whereNumber('expense')->name('expenses.destroy');
    Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
    Route::post('/savings', [SavingsController::class, 'store'])->name('savings.store');
    Route::put('/savings/{saving}', [SavingsController::class, 'update'])->whereNumber('saving')->name('savings.update');
    Route::delete('/savings/{saving}', [SavingsController::class, 'destroy'])->whereNumber('saving')->name('savings.destroy');
    Route::post('/savings/goals', [SavingsController::class, 'storeGoal'])->name('savings.goals.store');
    Route::put('/savings/goals/{goal}', [SavingsController::class, 'updateGoal'])->whereNumber('goal')->name('savings.goals.update');
    Route::delete('/savings/goals/{goal}', [SavingsController::class, 'destroyGoal'])->whereNumber('goal')->name('savings.goals.destroy');
    Route::get('/debts', [DebtController::class, 'index'])->name('debts.index');
    Route::post('/debts', [DebtController::class, 'store'])->name('debts.store');
    Route::put('/debts/{debt}', [DebtController::class, 'update'])->whereNumber('debt')->name('debts.update');
    Route::delete('/debts/{debt}', [DebtController::class, 'destroy'])->whereNumber('debt')->name('debts.destroy');
    Route::get('/recurring', [RecurringTransactionController::class, 'index'])->name('recurring.index');
    Route::post('/recurring', [RecurringTransactionController::class, 'store'])->name('recurring.store');
    Route::put('/recurring/{recurring}', [RecurringTransactionController::class, 'update'])->whereNumber('recurring')->name('recurring.update');
    Route::patch('/recurring/{recurring}/toggle', [RecurringTransactionController::class, 'toggle'])->whereNumber('recurring')->name('recurring.toggle');
    Route::delete('/recurring/{recurring}', [RecurringTransactionController::class, 'destroy'])->whereNumber('recurring')->name('recurring.destroy');
    Route::post('/recurring/process', [RecurringTransactionController::class, 'process'])->middleware('throttle:10,1')->name('recurring.process');
});
