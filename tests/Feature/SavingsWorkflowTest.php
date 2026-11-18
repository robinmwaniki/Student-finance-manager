<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SavingsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_goal_and_contribution_updates_its_balance(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('savings.goals.store'), [
            'title' => 'Emergency fund',
            'target_amount' => '1000.00',
            'due_date' => now()->addMonth()->toDateString(),
            'notes' => 'Keep for emergencies',
        ])->assertRedirect();

        $goal = $user->savingsGoals()->firstOrFail();
        $this->actingAs($user)->post(route('savings.store'), [
            'submission_token' => (string) Str::uuid(),
            'goal_id' => $goal->id,
            'amount' => '400.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('savings_goals', [
            'id' => $goal->id,
            'current_amount' => '400.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('savings', [
            'user_id' => $user->id,
            'goal_id' => $goal->id,
            'goal' => 'Emergency fund',
            'amount' => '400.00',
        ]);
    }

    public function test_replayed_contribution_token_does_not_create_a_duplicate(): void
    {
        $user = $this->makeUser();
        $goal = $user->savingsGoals()->create(['title' => 'Laptop', 'target_amount' => '50000.00']);
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'goal_id' => $goal->id,
            'amount' => '500.00',
            'transaction_date' => now()->toDateString(),
        ];

        $this->actingAs($user)->post(route('savings.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('savings.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('savings', 1);
        $this->assertDatabaseHas('savings_goals', ['id' => $goal->id, 'current_amount' => '500.00']);
    }

    public function test_editing_and_deleting_contributions_reconciles_goal_balance(): void
    {
        $user = $this->makeUser();
        $goal = $user->savingsGoals()->create(['title' => 'Fees', 'target_amount' => '1000.00']);
        $saving = $user->savings()->create([
            'goal_id' => $goal->id,
            'goal' => $goal->title,
            'amount' => '300.00',
            'transaction_date' => now()->toDateString(),
            'savings_month' => now()->format('Y-m'),
        ]);

        $this->actingAs($user)->put(route('savings.update', $saving), [
            'lock_version' => 0,
            'goal_id' => $goal->id,
            'amount' => '700.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('savings_goals', ['id' => $goal->id, 'current_amount' => '700.00']);

        $this->actingAs($user)->delete(route('savings.destroy', $saving), [
            'lock_version' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('savings_goals', ['id' => $goal->id, 'current_amount' => '0.00']);
    }

    public function test_contribution_that_reaches_target_completes_goal(): void
    {
        $user = $this->makeUser();
        $goal = $user->savingsGoals()->create(['title' => 'Books', 'target_amount' => '2500.00']);

        $this->actingAs($user)->post(route('savings.store'), [
            'submission_token' => (string) Str::uuid(),
            'goal_id' => $goal->id,
            'amount' => '2500.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('savings_goals', [
            'id' => $goal->id,
            'current_amount' => '2500.00',
            'status' => 'completed',
        ]);
    }

    public function test_user_cannot_attach_a_contribution_to_another_users_goal(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $goal = $owner->savingsGoals()->create(['title' => 'Private goal']);

        $this->actingAs($other)->from(route('savings.index'))->post(route('savings.store'), [
            'submission_token' => (string) Str::uuid(),
            'goal_id' => $goal->id,
            'amount' => '100.00',
            'transaction_date' => now()->toDateString(),
        ])->assertSessionHasErrors('goal_id');

        $this->assertDatabaseCount('savings', 0);
    }

    public function test_user_cannot_update_another_users_contribution(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $saving = $owner->savings()->create([
            'goal' => 'General savings',
            'amount' => '100.00',
            'transaction_date' => now()->toDateString(),
            'savings_month' => now()->format('Y-m'),
        ]);

        $this->actingAs($other)->put(route('savings.update', $saving), [
            'lock_version' => 0,
            'goal' => 'Tampered',
            'amount' => '1.00',
            'transaction_date' => now()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseHas('savings', ['id' => $saving->id, 'user_id' => $owner->id, 'amount' => '100.00']);
    }

    public function test_deleting_goal_preserves_contribution_history(): void
    {
        $user = $this->makeUser();
        $goal = $user->savingsGoals()->create(['title' => 'Trip']);
        $saving = $user->savings()->create([
            'goal_id' => $goal->id,
            'goal' => 'Trip',
            'amount' => '150.00',
            'transaction_date' => now()->toDateString(),
            'savings_month' => now()->format('Y-m'),
        ]);

        $this->actingAs($user)->delete(route('savings.goals.destroy', $goal), ['lock_version' => 0])->assertRedirect();

        $this->assertDatabaseHas('savings', ['id' => $saving->id, 'goal_id' => null, 'goal' => 'Trip']);
    }

    public function test_stale_contribution_update_is_rejected(): void
    {
        $user = $this->makeUser();
        $saving = $user->savings()->create([
            'goal' => 'General',
            'amount' => '100.00',
            'transaction_date' => now()->toDateString(),
            'savings_month' => now()->format('Y-m'),
        ]);

        $payload = ['lock_version' => 0, 'goal' => 'Updated', 'amount' => '200.00', 'transaction_date' => now()->toDateString()];
        $this->actingAs($user)->put(route('savings.update', $saving), $payload)->assertRedirect();
        $this->actingAs($user)->put(route('savings.update', $saving), $payload)->assertStatus(409);
    }

    private function makeUser(string $email = 'savings@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Savings Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
