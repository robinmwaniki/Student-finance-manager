<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BillSplitService;
use App\Services\CurrencyRateService;
use App\Services\LoanCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_calculator_returns_a_balanced_monthly_amortisation_schedule(): void
    {
        $result = app(LoanCalculatorService::class)->calculate(100000, 12, 12, 'monthly');

        $this->assertCount(12, $result['schedule']);
        $this->assertGreaterThan(0, $result['total_interest']);
        $this->assertEqualsWithDelta(0, $result['schedule'][11]['balance'], 0.01);
        $this->assertEqualsWithDelta($result['total_paid'], array_sum(array_column($result['schedule'], 'payment')), 0.01);
        $this->assertEqualsWithDelta($result['total_interest'], array_sum(array_column($result['schedule'], 'interest')), 0.01);
    }

    public function test_zero_interest_loan_has_no_interest_and_weekly_frequency_is_supported(): void
    {
        $calculator = app(LoanCalculatorService::class);
        $zeroRate = $calculator->calculate(12000, 0, 12, 'monthly');
        $weekly = $calculator->calculate(12000, 8, 3, 'weekly');

        $this->assertEqualsWithDelta(0.0, $zeroRate['total_interest'], 0.001);
        $this->assertEqualsWithDelta(12000, $zeroRate['total_paid'], 0.01);
        $this->assertGreaterThan(12, $weekly['periods']);
        $this->assertEqualsWithDelta(0, $weekly['schedule'][$weekly['periods'] - 1]['balance'], 0.01);
    }

    public function test_bill_split_distributes_remainder_cents_without_losing_money(): void
    {
        $result = app(BillSplitService::class)->split(1000, 3, 10);

        $this->assertEqualsWithDelta(1100.0, $result['total'], 0.001);
        $this->assertEqualsWithDelta(1100, array_sum($result['shares']), 0.001);
        $this->assertEqualsWithDelta(366.67, $result['shares'][0], 0.001);
        $this->assertEqualsWithDelta(366.67, $result['shares'][1], 0.001);
        $this->assertEqualsWithDelta(366.66, $result['shares'][2], 0.001);
    }

    public function test_currency_rates_are_cached_and_include_provider_timestamp(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'time_last_update_utc' => 'Thu, 19 Nov 2026 00:00:01 +0000',
                'rates' => ['KES' => 130.5, 'USD' => 1],
            ], 200),
        ]);

        $quote = app(CurrencyRateService::class)->quote('USD', 'KES');

        $this->assertEquals(130.5, $quote['rate']);
        $this->assertFalse($quote['stale']);
        $this->assertStringContainsString('19 Nov 2026', $quote['updated_at']);
        Http::assertSentCount(1);

        app(CurrencyRateService::class)->quote('USD', 'KES');
        Http::assertSentCount(1);
    }

    public function test_currency_service_uses_explicitly_stale_cache_when_provider_fails(): void
    {
        Cache::put('public-fx:stale:USD', [
            'rates' => ['KES' => 129.25],
            'updated_at' => 'Thu, 12 Nov 2026 00:00:01 +0000',
        ], now()->addDays(7));
        Http::fake(['*' => Http::response([], 503)]);

        $quote = app(CurrencyRateService::class)->quote('USD', 'KES');

        $this->assertEquals(129.25, $quote['rate']);
        $this->assertTrue($quote['stale']);
    }

    public function test_currency_service_returns_unavailable_when_no_fresh_or_cached_rate_exists(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->assertNull(app(CurrencyRateService::class)->quote('USD', 'KES'));
    }

    public function test_tools_page_displays_the_three_calculators(): void
    {
        $user = User::create([
            'fullname' => 'Tools User',
            'email' => 'tools-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->actingAs($user)->get(route('tools.index'))
            ->assertOk()
            ->assertSee('Loan and interest calculator')
            ->assertSee('Split a bill')
            ->assertSee('Currency converter');
    }

    public function test_calculator_routes_return_results_in_the_session(): void
    {
        $user = User::create([
            'fullname' => 'Tools User',
            'email' => 'tools-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->actingAs($user)->post(route('tools.loan'), [
            'principal' => 100000,
            'annual_rate' => 12,
            'term_months' => 12,
            'frequency' => 'monthly',
        ])->assertRedirect()->assertSessionHas('loan_result');

        $this->actingAs($user)->post(route('tools.split'), [
            'subtotal' => 1000,
            'participants' => 3,
            'tip_percent' => 10,
        ])->assertRedirect()->assertSessionHas('split_result');
    }

    public function test_currency_converter_route_returns_amount_and_rate_provenance(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'time_last_update_utc' => 'Thu, 19 Nov 2026 00:00:01 +0000',
                'rates' => ['KES' => 130, 'USD' => 1],
            ], 200),
        ]);
        $user = User::create([
            'fullname' => 'Tools User',
            'email' => 'tools-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->actingAs($user)->post(route('tools.currency'), [
            'amount' => '2.50',
            'from' => 'USD',
            'to' => 'KES',
        ])->assertRedirect()->assertSessionHas('currency_result', function (array $result): bool {
            return $result['converted'] === 325.0
                && $result['rate'] === 130.0
                && $result['source'] === 'open.er-api.com (Open Access)';
        });
    }

    public function test_financial_tools_page_and_calculator_routes_require_authentication(): void
    {
        $this->get(route('tools.index'))->assertRedirect(route('login'));
        $this->post(route('tools.loan'), [
            'principal' => 100000,
            'annual_rate' => 12,
            'term_months' => 12,
            'frequency' => 'monthly',
        ])->assertRedirect(route('login'));
    }
}
