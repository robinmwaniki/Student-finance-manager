<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LegacySchemaFixtureTest extends TestCase
{
    public function test_captured_legacy_schema_fixture_is_ddl_only_and_covers_the_supplied_schema(): void
    {
        $fixture = file_get_contents(base_path('tests/Fixtures/legacy-finance-schema.sql'));

        $this->assertIsString($fixture);
        $this->assertStringNotContainsString('INSERT INTO', strtoupper($fixture));
        $this->assertStringNotContainsString('@gmail.com', $fixture);
        $this->assertDoesNotMatchRegularExpression('/\\$2[aby]\\$\\d+\\$/', $fixture);

        preg_match_all('/CREATE TABLE \x60([^\x60]+)\x60/i', $fixture, $matches);

        $this->assertEqualsCanonicalizing([
            'achievements',
            'bill_reminders',
            'budget',
            'debts',
            'expenses',
            'financial_notes',
            'income',
            'notifications',
            'quiz_attempts',
            'quiz_streaks',
            'recurring_log',
            'recurring_transactions',
            'savings',
            'savings_goals',
            'users',
        ], $matches[1]);
    }
}
