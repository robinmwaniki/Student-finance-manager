<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_currency_expense_is_converted_to_kes_and_original_metadata_is_preserved(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'time_last_update_utc' => 'Thu, 19 Nov 2026 00:00:01 +0000',
                'rates' => ['KES' => 130, 'USD' => 1],
            ], 200),
        ]);
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('expenses.store'), $this->expensePayload([
            'amount' => '10.00',
            'currency' => 'USD',
        ]))->assertRedirect();

        $expense = $user->expenses()->firstOrFail();
        $this->assertSame('1300.00', $expense->amount);
        $this->assertSame('10.00', $expense->original_amount);
        $this->assertSame('USD', $expense->original_currency);
        $this->assertSame('130.0000000000', $expense->exchange_rate);
        $this->assertSame('1300.00', $expense->converted_amount);
        $this->assertEquals(1300, $user->expenses()->sum('amount'));
    }

    public function test_kes_expense_defaults_to_one_to_one_and_legacy_requests_remain_supported(): void
    {
        $user = $this->makeUser();
        $payload = $this->expensePayload(['amount' => '250.00']);
        unset($payload['currency']);

        $this->actingAs($user)->post(route('expenses.store'), $payload)->assertRedirect();

        $expense = $user->expenses()->firstOrFail();
        $this->assertSame('250.00', $expense->amount);
        $this->assertSame('KES', $expense->original_currency);
        $this->assertSame('1.0000000000', $expense->exchange_rate);
    }

    public function test_stale_rate_requires_explicit_confirmation_before_expense_is_saved(): void
    {
        Cache::put('public-fx:stale:USD', [
            'rates' => ['KES' => 128.5],
            'updated_at' => 'Thu, 12 Nov 2026 00:00:01 +0000',
        ], now()->addDays(7));
        Http::fake(['*' => Http::response([], 503)]);
        $user = $this->makeUser();
        $payload = $this->expensePayload(['amount' => '5.00', 'currency' => 'USD']);

        $this->actingAs($user)->post(route('expenses.store'), $payload)
            ->assertSessionHasErrors('currency')
            ->assertSessionHas('stale_expense_quote');
        $this->assertSame(0, $user->expenses()->count());

        $payload['accept_stale_rate'] = '1';
        $this->actingAs($user)->post(route('expenses.store'), $payload)->assertRedirect();

        $expense = $user->expenses()->firstOrFail();
        $this->assertSame('642.50', $expense->amount);
        $this->assertSame('USD', $expense->original_currency);
    }

    public function test_provider_failure_without_cached_rate_does_not_create_foreign_currency_expense(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('expenses.store'), $this->expensePayload([
            'amount' => '20.00',
            'currency' => 'EUR',
        ]))->assertSessionHasErrors('currency');

        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_editing_expense_in_kes_resets_conversion_metadata_consistently(): void
    {
        $user = $this->makeUser();
        $expense = $user->expenses()->create([
            'category' => 'Travel',
            'amount' => '1300.00',
            'original_amount' => '10.00',
            'original_currency' => 'USD',
            'exchange_rate' => 130,
            'converted_amount' => '1300.00',
            'transaction_date' => now()->toDateString(),
            'submission_token' => (string) Str::uuid(),
        ]);

        $this->actingAs($user)->put(route('expenses.update', $expense), [
            'lock_version' => 0,
            'category' => 'Travel',
            'amount' => '1500.00',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect();

        $expense->refresh();
        $this->assertSame('KES', $expense->original_currency);
        $this->assertSame('1500.00', $expense->original_amount);
        $this->assertSame('1.0000000000', $expense->exchange_rate);
    }

    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'submission_token' => (string) Str::uuid(),
            'category' => 'Travel',
            'amount' => '100.00',
            'currency' => 'KES',
            'transaction_date' => now(config('app.timezone'))->toDateString(),
            'tag' => 'Test',
        ], $overrides);
    }

    private function makeUser(): User
    {
        return User::create([
            'fullname' => 'Currency Test User',
            'email' => 'currency-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ]);
    }
}
