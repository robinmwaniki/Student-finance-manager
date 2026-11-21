<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_receives_a_default_kes_wallet(): void
    {
        $user = $this->makeUser();

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'name' => 'Main wallet',
            'type' => 'cash',
            'currency' => 'KES',
            'is_default' => true,
        ]);
    }

    public function test_income_and_expense_are_assigned_to_the_selected_wallet(): void
    {
        $user = $this->makeUser();
        $wallet = $user->wallets()->create([
            'name' => 'M-Pesa',
            'type' => 'mpesa',
            'currency' => 'KES',
            'opening_balance' => 0,
            'is_default' => false,
        ]);

        $this->actingAs($user)->post(route('income.store'), [
            'submission_token' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'source' => 'Allowance',
            'amount' => '500.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($user)->post(route('expenses.store'), [
            'submission_token' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'category' => 'Transport',
            'amount' => '120.00',
            'currency' => 'KES',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('income', ['user_id' => $user->id, 'wallet_id' => $wallet->id, 'amount' => '500.00']);
        $this->assertDatabaseHas('expenses', ['user_id' => $user->id, 'wallet_id' => $wallet->id, 'amount' => '120.00']);
        $this->assertSame('380.00', $wallet->fresh()->balance);
    }

    public function test_transfer_moves_money_without_creating_income_or_expense(): void
    {
        $user = $this->makeUser();
        $from = $user->wallets()->where('is_default', true)->firstOrFail();
        $from->update(['opening_balance' => '1000.00']);
        $to = $user->wallets()->create([
            'name' => 'M-Pesa',
            'type' => 'mpesa',
            'currency' => 'KES',
            'opening_balance' => '100.00',
            'is_default' => false,
        ]);

        $this->actingAs($user)->post(route('wallets.transfers.store'), [
            'submission_token' => (string) Str::uuid(),
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => '300.00',
            'transfer_date' => now()->toDateString(),
            'note' => 'Move spending money',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('700.00', $from->fresh()->balance);
        $this->assertSame('400.00', $to->fresh()->balance);
        $this->assertDatabaseCount('wallet_transfers', 1);
        $this->assertDatabaseCount('income', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_transfer_is_rejected_when_source_wallet_has_insufficient_funds(): void
    {
        $user = $this->makeUser();
        $from = $user->wallets()->where('is_default', true)->firstOrFail();
        $to = $user->wallets()->create([
            'name' => 'Bank',
            'type' => 'bank',
            'currency' => 'KES',
            'opening_balance' => 0,
            'is_default' => false,
        ]);

        $this->actingAs($user)->post(route('wallets.transfers.store'), [
            'submission_token' => (string) Str::uuid(),
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => '1.00',
            'transfer_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('wallet_transfers', 0);
        $this->assertSame('0.00', $from->fresh()->balance);
    }

    public function test_user_cannot_transfer_to_another_users_wallet(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $from = $owner->wallets()->where('is_default', true)->firstOrFail();
        $foreignWallet = $other->wallets()->where('is_default', true)->firstOrFail();

        $this->actingAs($owner)->post(route('wallets.transfers.store'), [
            'submission_token' => (string) Str::uuid(),
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $foreignWallet->id,
            'amount' => '1.00',
            'transfer_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasErrors('from_wallet_id');

        $this->assertDatabaseCount('wallet_transfers', 0);
    }

    private function makeUser(string $email = 'wallet-owner@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Wallet Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
