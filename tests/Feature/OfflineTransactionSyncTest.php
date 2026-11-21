<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineTransactionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_sync_is_idempotent_when_the_same_offline_item_is_delivered_twice(): void
    {
        $user = $this->makeUser();
        $token = (string) Str::uuid();
        $payload = [
            'submission_token' => $token,
            'source' => 'Allowance',
            'amount' => '1250.00',
            'transaction_date' => now(config('app.timezone'))->toDateString(),
            'tag' => 'Offline',
        ];

        $first = $this->actingAs($user)->withSession(['password_hash_web' => $user->getAuthPassword()])->postJson(route('income.store'), $payload)->assertCreated();
        $second = $this->actingAs($user)->postJson(route('income.store'), $payload)->assertCreated();

        $this->assertSame($first->json('record_id'), $second->json('record_id'));
        $this->assertSame(1, $user->income()->count());
    }

    public function test_expense_sync_is_idempotent_and_returns_json_for_offline_delivery(): void
    {
        $user = $this->makeUser();
        $payload = [
            'submission_token' => (string) Str::uuid(),
            'category' => 'Transport',
            'amount' => '180.00',
            'currency' => 'KES',
            'transaction_date' => now(config('app.timezone'))->toDateString(),
            'tag' => 'Offline',
        ];

        $first = $this->actingAs($user)->postJson(route('expenses.store'), $payload)->assertCreated();
        $second = $this->actingAs($user)->postJson(route('expenses.store'), $payload)->assertCreated();

        $this->assertSame($first->json('record_id'), $second->json('record_id'));
        $this->assertSame(1, $user->expenses()->count());
        $this->assertSame('180.00', $user->expenses()->firstOrFail()->amount);
    }

    public function test_invalid_offline_expense_is_rejected_as_json_and_is_not_persisted(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('expenses.store'), [
            'submission_token' => (string) Str::uuid(),
            'category' => 'Transport',
            'amount' => '-1.00',
            'currency' => 'KES',
            'transaction_date' => now(config('app.timezone'))->toDateString(),
        ])->assertUnprocessable();

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_offline_outbox_amount_editor_accepts_plain_decimal_values(): void
    {
        $script = file_get_contents(public_path('offline-transactions.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('/^\\d+(?:\\.\\d{1,2})?$/', $script);
    }

    private function makeUser(): User
    {
        return User::create([
            'fullname' => 'Offline Sync User',
            'email' => 'offline-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ]);
    }
}
