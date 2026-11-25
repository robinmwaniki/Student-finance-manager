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
}
