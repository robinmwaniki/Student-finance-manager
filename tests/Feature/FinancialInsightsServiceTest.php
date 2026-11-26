<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FinancialInsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_are_calculated_from_the_authenticated_users_records_only(): void
    {
        $user = $this->makeUser('insights@example.test');
        $other = $this->makeUser('other-insights@example.test');
        $today = now()->toDateString();

        $user->income()->create(['source' => 'Salary', 'amount' => '10000.00', 'transaction_date' => $today]);
        $user->expenses()->create(['category' => 'Transport', 'amount' => '2000.00', 'transaction_date' => $today]);
        $other->income()->create(['source' => 'Other', 'amount' => '90000.00', 'transaction_date' => $today]);
        $other->expenses()->create(['category' => 'Private', 'amount' => '80000.00', 'transaction_date' => $today]);

        $result = app(FinancialInsightsService::class)->forUser($user);

        $this->assertSame(10000.0, $result['income']);
        $this->assertSame(2000.0, $result['expenses']);
        $this->assertSame(8000.0, $result['net']);
        $this->assertSame('Transport', $result['top_categories'][0]['category']);
        $this->assertNotContains('Private', array_column($result['top_categories'], 'category'));
    }

    public function test_insights_offer_an_optional_savings_target_when_there_is_room(): void
    {
        $user = $this->makeUser('savings-insights@example.test');
        $today = now()->toDateString();
        $user->income()->create(['source' => 'Income', 'amount' => '10000.00', 'transaction_date' => $today]);
        $user->expenses()->create(['category' => 'Food', 'amount' => '3000.00', 'transaction_date' => $today]);

        $result = app(FinancialInsightsService::class)->forUser($user);

        $this->assertSame(10000.0, $result['income']);
        $this->assertSame(3000.0, $result['expenses']);
        $this->assertStringContainsString('optional target', implode(' ', array_column($result['recommendations'], 'detail')));
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'fullname' => 'Finance Insights Test',
            'email' => $email,
            'password' => 'StrongPassword123',
        ]);
    }
}
