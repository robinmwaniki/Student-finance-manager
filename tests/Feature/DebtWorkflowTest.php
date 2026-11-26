<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebtWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_view_a_debt_record(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('debts.store'), [
            'submission_token' => (string) Str::uuid(),
            'description' => 'Tuition loan',
            'amount' => '12500.50',
            'type' => 'borrowed',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('debts', [
            'user_id' => $user->id,
            'description' => 'Tuition loan',
            'amount' => '12500.50',
            'type' => 'borrowed',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->get(route('debts.index'))->assertOk()->assertSee('Tuition loan');
    }

    public function test_duplicate_submission_token_does_not_create_duplicate_debt(): void
    {
        $user = $this->makeUser();
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'description' => 'Laptop loan',
            'amount' => '5000.00',
            'type' => 'borrowed',
            'due_date' => null,
        ];

        $this->actingAs($user)->post(route('debts.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('debts.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('debts', 1);
    }

    public function test_user_can_filter_debts_by_type_and_status(): void
    {
        $user = $this->makeUser();
        $user->debts()->create(['description' => 'Borrowed cash', 'amount' => '100.00', 'type' => 'borrowed', 'status' => 'pending']);
        $user->debts()->create(['description' => 'Money lent', 'amount' => '200.00', 'type' => 'lent', 'status' => 'pending']);
        $user->debts()->create(['description' => 'Settled loan', 'amount' => '300.00', 'type' => 'borrowed', 'status' => 'paid']);

        $this->actingAs($user)->get(route('debts.index', ['type' => 'lent']))->assertOk()
            ->assertSee('Money lent')->assertDontSee('Borrowed cash');
        $this->actingAs($user)->get(route('debts.index', ['status' => 'paid']))->assertOk()
            ->assertSee('Settled loan')->assertDontSee('Borrowed cash');
    }

    public function test_overdue_filter_uses_pending_status_and_past_due_date(): void
    {
        $user = $this->makeUser();
        $user->debts()->create([
            'description' => 'Late payment',
            'amount' => '750.00',
            'type' => 'borrowed',
            'status' => 'pending',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $user->debts()->create([
            'description' => 'Future payment',
            'amount' => '950.00',
            'type' => 'borrowed',
            'status' => 'pending',
            'due_date' => now()->addDay()->toDateString(),
        ]);
        $user->debts()->create([
            'description' => 'Paid late debt',
            'amount' => '150.00',
            'type' => 'borrowed',
            'status' => 'paid',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)->get(route('debts.index', ['status' => 'overdue']))->assertOk()
            ->assertSee('Late payment')->assertDontSee('Future payment')->assertDontSee('Paid late debt');
    }

    public function test_debt_status_cannot_be_manually_marked_paid(): void
    {
        $user = $this->makeUser();
        $debt = $user->debts()->create([
            'description' => 'Personal loan',
            'amount' => '1200.00',
            'type' => 'borrowed',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->put(route('debts.update', $debt), [
            'lock_version' => 0,
            'description' => 'Personal loan',
            'amount' => '1200.00',
            'type' => 'borrowed',
            'due_date' => null,
            'status' => 'paid',
        ])->assertRedirect();

        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'status' => 'pending',
            'lock_version' => 1,
        ]);
    }

    public function test_user_cannot_access_or_modify_another_users_debt(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $debt = $owner->debts()->create([
            'description' => 'Private debt',
            'amount' => '900.00',
            'type' => 'borrowed',
            'status' => 'pending',
        ]);

        $this->actingAs($other)->put(route('debts.update', $debt), [
            'lock_version' => 0,
            'description' => 'Changed',
            'amount' => '1.00',
            'type' => 'lent',
            'due_date' => null,
            'status' => 'pending',
        ])->assertNotFound();

        $this->actingAs($other)->delete(route('debts.destroy', $debt), ['lock_version' => 0])->assertNotFound();
        $this->assertDatabaseHas('debts', ['id' => $debt->id, 'user_id' => $owner->id, 'description' => 'Private debt']);
    }

    public function test_stale_update_and_delete_are_rejected(): void
    {
        $user = $this->makeUser();
        $debt = $user->debts()->create([
            'description' => 'Shared bill',
            'amount' => '300.00',
            'type' => 'lent',
            'status' => 'pending',
        ]);
        $payload = [
            'lock_version' => 0,
            'description' => 'Updated bill',
            'amount' => '350.00',
            'type' => 'lent',
            'due_date' => null,
            'status' => 'pending',
        ];

        $this->actingAs($user)->put(route('debts.update', $debt), $payload)->assertRedirect();
        $this->actingAs($user)->put(route('debts.update', $debt), $payload)->assertStatus(409);
        $this->actingAs($user)->delete(route('debts.destroy', $debt), ['lock_version' => 0])->assertStatus(409);
    }

    public function test_non_positive_amount_and_unknown_type_are_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->from(route('debts.index'))->post(route('debts.store'), [
            'submission_token' => (string) Str::uuid(),
            'description' => 'Invalid debt',
            'amount' => '0.00',
            'type' => 'unknown',
        ])->assertSessionHasErrors(['amount', 'type']);

        $this->assertDatabaseCount('debts', 0);
    }

    public function test_paid_debt_has_no_outstanding_balance_without_repayment_rows(): void
    {
        $user = $this->makeUser();
        $debt = $user->debts()->create([
            'description' => 'Legacy settled debt',
            'amount' => '1250.00',
            'type' => 'borrowed',
            'status' => 'paid',
        ]);

        $this->assertSame(0, $debt->fresh()->outstandingBalanceCents());
    }

    private function makeUser(string $email = 'debt@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Debt Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
