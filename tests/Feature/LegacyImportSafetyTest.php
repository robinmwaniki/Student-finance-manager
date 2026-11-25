<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyImportSafetyTest extends TestCase
{
    public function test_legacy_import_refuses_to_run_outside_the_staging_environment(): void
    {
        $this->artisan('finance:import-legacy')
            ->expectsOutputToContain('Imports are permitted only when APP_ENV is staging.')
            ->assertExitCode(1);
    }
    public function test_legacy_import_rejects_production_named_target_databases(): void
    {
        Config::set('app.env', 'staging');
        Config::set('legacy-import.allowed_target_databases', ['student_finance_prod']);
        Config::set('database.connections.legacy_mysql', [
            'driver' => 'mysql',
            'host' => 'legacy-staging-host',
            'database' => 'legacy_finance',
        ]);
        Config::set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => 'postgres-staging-host',
            'database' => 'student_finance_prod',
        ]);

        $this->artisan('finance:import-legacy')
            ->expectsOutputToContain('Production-looking target database names are forbidden.')
            ->assertExitCode(1);
    }

    public function test_target_database_url_cannot_override_the_staging_allowlist(): void
    {
        Config::set('app.env', 'staging');
        Config::set('legacy-import.allowed_target_databases', ['student_finance_staging']);
        Config::set('database.connections.legacy_mysql', [
            'driver' => 'mysql',
            'host' => 'legacy-staging-host',
            'database' => 'legacy_finance',
        ]);
        Config::set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => 'postgres-staging-host',
            'database' => 'student_finance_staging',
            'url' => 'postgresql://user:password@postgres.example.test/student_finance_prod',
        ]);

        $this->artisan('finance:import-legacy')
            ->expectsOutputToContain('Resolved target database is not on LEGACY_IMPORT_ALLOWED_TARGET_DATABASES.')
            ->assertExitCode(1);
    }
}
