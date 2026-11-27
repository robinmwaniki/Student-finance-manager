<?php

namespace Tests\Unit;

use Tests\TestCase;

class LegacySchemaFixtureTest extends TestCase
{
    public function test_captured_legacy_schema_fixture_is_ddl_only_and_covers_the_supplied_schema(): void
    {
        $fixture = file_get_contents(base_path('tests/Fixtures/legacy-finance-schema.sql'));

        $this->assertIsString($fixture);
        $this->assertStringNotContainsString('INSERT INTO', strtoupper($fixture));
        $this->assertDoesNotMatchRegularExpression('/@[a-z0-9.-]+\.[a-z]{2,}/i', $fixture);
        $this->assertDoesNotMatchRegularExpression('/\$2[aby]\$\d+\$/', $fixture);

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

    public function test_import_mapping_columns_exist_in_the_captured_legacy_schema(): void
    {
        $fixture = file_get_contents(base_path('tests/Fixtures/legacy-finance-schema.sql'));
        $this->assertIsString($fixture);

        preg_match_all(
            '/CREATE TABLE\s+\x60([^\x60]+)\x60\s*\((.*?)\)\s*ENGINE=/is',
            $fixture,
            $tableMatches,
            PREG_SET_ORDER
        );

        $schema = [];
        foreach ($tableMatches as $tableMatch) {
            preg_match_all('/^\s*\x60([^\x60]+)\x60\s+/m', $tableMatch[2], $columnMatches);
            $schema[$tableMatch[1]] = $columnMatches[1];
        }

        $requiredSourceColumns = [
            'users' => ['id', 'fullname', 'email', 'password', 'points', 'avatar'],
            'income' => ['id', 'user_id', 'amount', 'source', 'date', 'tag'],
            'expenses' => ['id', 'user_id', 'category', 'amount', 'date', 'tag'],
            'budget' => ['id', 'user_id', 'category', 'amount', 'limit_amount', 'budget_month', 'date'],
            'savings_goals' => ['id', 'user_id', 'title', 'target_amount', 'current_amount', 'due_date', 'status', 'notes'],
            'savings' => ['id', 'user_id', 'goal', 'amount', 'date', 'savings_month'],
            'debts' => ['id', 'user_id', 'description', 'amount', 'type', 'due_date', 'status'],
            'achievements' => ['id', 'user_id', 'title', 'description', 'points', 'unlocked_at'],
            'notifications' => ['id', 'user_id', 'message', 'status', 'created_at', 'type', 'read_at'],
            'recurring_transactions' => ['id', 'user_id', 'category', 'amount', 'type', 'frequency', 'next_due', 'status', 'label', 'day_of_month', 'active'],
            'recurring_log' => ['id', 'recurring_id', 'user_id', 'applied_month', 'applied_at'],
        ];

        $this->assertSame([
            'users',
            'income',
            'expenses',
            'budget',
            'savings_goals',
            'savings',
            'debts',
            'achievements',
            'notifications',
            'recurring_transactions',
            'recurring_log',
        ], array_keys($requiredSourceColumns));

        foreach ($requiredSourceColumns as $table => $columns) {
            $this->assertArrayHasKey($table, $schema, "Missing imported legacy table: {$table}");
            foreach ($columns as $column) {
                $this->assertContains(
                    $column,
                    $schema[$table],
                    "The captured legacy table {$table} does not contain mapped source column {$column}."
                );
            }
        }

        foreach (['bill_reminders', 'financial_notes', 'quiz_attempts', 'quiz_streaks'] as $outOfScopeTable) {
            $this->assertArrayHasKey($outOfScopeTable, $schema);
            $this->assertArrayNotHasKey($outOfScopeTable, $requiredSourceColumns);
        }
    }
}
