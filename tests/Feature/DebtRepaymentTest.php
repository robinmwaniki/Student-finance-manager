<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebtRepaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_repayments_update_partial_and_paid_balances(): void
    {
        $user = $this->user();
        $debt = $user->debts()->create(['description' => 'Tuition', 'amount' => '1000.00', 'type' => 'borrowed', 'status' => 'pending']);
        $this->actingAs($user)->post(route('debts.repayments.store', $debt), [
            'submission_token' => (string) Str::uuid(), 'amount' => '400.00', 'repayment_date' => today()->toDateString(),
        ])->assertRedirect();
        $this->assertSame('partially_paid', $debt->fresh()->status);
        $this->assertSame(60000, $debt->fresh()->outstandingBalanceCents());
        $this->actingAs($user)->post(route('debts.repayments.store', $debt), [
            'submission_token' => (string) Str::uuid(), 'amount' => '600.00', 'repayment_date' => today()->toDateString(),
        ])->assertRedirect();
        $this->assertSame('paid', $debt->fresh()->status);
        $this->assertDatabaseCount('debt_repayments', 2);
    }

    public function test_duplicate_repayment_submission_is_idempotent(): void
    {
        $user = $this->user();
        $debt = $user->debts()->create(['description' => 'Loan', 'amount' => '100.00', 'type' => 'borrowed', 'status' => 'pending']);
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'amount' => '60.00',
            'repayment_date' => today()->toDateString(),
        ];

        $this->actingAs($user)->post(route('debts.repayments.store', $debt), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('debts.repayments.store', $debt), $payload)->assertRedirect();

        $this->assertDatabaseCount('debt_repayments', 1);
        $this->assertSame(4000, $debt->fresh()->outstandingBalanceCents());
    }

    public function test_overpayment_is_rejected_without_changing_the_balance(): void
    {
        $user = $this->user();
        $debt = $user->debts()->create(['description' => 'Loan', 'amount' => '100.00', 'type' => 'borrowed', 'status' => 'pending']);

        $this->actingAs($user)->post(route('debts.repayments.store', $debt), [
            'submission_token' => (string) Str::uuid(),
            'amount' => '100.01',
            'repayment_date' => today()->toDateString(),
        ])->assertRedirect()->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('debt_repayments', 0);
        $this->assertSame(10000, $debt->fresh()->outstandingBalanceCents());
    }

    public function test_users_cannot_repay_another_users_debt(): void
    {
        $owner = $this->user('owner@example.test');
        $other = $this->user('other@example.test');
        $debt = $owner->debts()->create(['description' => 'Private', 'amount' => '100.00', 'type' => 'borrowed', 'status' => 'pending']);
        $this->actingAs($other)->post(route('debts.repayments.store', $debt), [
            'submission_token' => (string) Str::uuid(), 'amount' => '10.00', 'repayment_date' => today()->toDateString(),
        ])->assertNotFound();
        $this->assertDatabaseCount('debt_repayments', 0);
    }

    public function test_repayment_history_prevents_deletion_and_lowering_original_amount(): void
    {
        $user = $this->user();
        $debt = $user->debts()->create(['description' => 'Loan', 'amount' => '100.00', 'type' => 'borrowed', 'status' => 'pending']);
        $this->actingAs($user)->post(route('debts.repayments.store', $debt), [
            'submission_token' => (string) Str::uuid(), 'amount' => '70.00', 'repayment_date' => today()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($user)->put(route('debts.update', $debt), [
            'lock_version' => 1, 'description' => 'Loan', 'amount' => '60.00', 'type' => 'borrowed', 'due_date' => null,
        ])->assertStatus(422);
        $this->actingAs($user)->delete(route('debts.destroy', $debt), ['lock_version' => 1])->assertStatus(422);
        $this->assertDatabaseCount('debt_repayments', 1);
    }

    private function user(string $email = 'debt@example.test'): User
    {
        return User::create(['fullname' => 'Debt Owner', 'email' => $email, 'password' => 'StrongPassword123']);
    }
}
