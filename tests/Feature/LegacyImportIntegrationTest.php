<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyImportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private array $legacyTables = [
        'recurring_log',
        'recurring_transactions',
        'notifications',
        'achievements',
        'debts',
        'savings',
        'savings_goals',
        'budget',
        'expenses',
        'income',
        'users',
    ];

    private string $reportPath;

    public function test_synthetic_mariadb_rows_import_idempotently_into_postgresql_staging(): void
    {
        if (! extension_loaded('pdo_mysql') || ! config('database.connections.legacy_mysql.database')) {
            $this->markTestSkipped('A dedicated MariaDB test connection is not configured.');
        }

        $this->app->detectEnvironment(fn (): string => 'staging');
        $this->configureStagingAllowlist();
        $this->createLegacySchema();
        $this->seedSyntheticLegacyRows();

        $this->artisan('finance:import-legacy --execute --report=integration-legacy-import.json')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => 42,
            'email' => 'legacy-import@example.test',
            'points' => 7,
        ]);
        $importedUser = User::query()->findOrFail(42);
        $this->assertTrue(password_verify('SyntheticPass-123', $importedUser->password));
        $this->assertDatabaseHas('income', [
            'id' => 100,
            'user_id' => 42,
            'amount' => '1000.10',
        ]);
        $this->assertDatabaseHas('expenses', [
            'id' => 101,
            'user_id' => 42,
            'amount' => '100.10',
        ]);
        $this->assertDatabaseHas('budgets', [
            'id' => 102,
            'user_id' => 42,
            'category' => 'Food',
            'budget_month' => '2026-11',
        ]);
        $this->assertDatabaseHas('savings_goals', [
            'id' => 103,
            'user_id' => 42,
            'title' => 'Emergency fund',
        ]);
        $this->assertDatabaseHas('savings', [
            'id' => 104,
            'goal_id' => 103,
            'goal' => 'Emergency fund',
        ]);
        $this->assertDatabaseHas('debts', [
            'id' => 105,
            'type' => 'borrowed',
            'status' => 'paid',
        ]);
        $this->assertSame(0, Debt::query()->findOrFail(105)->outstandingBalanceCents());
        $this->assertDatabaseHas('achievements', [
            'id' => 106,
            'user_id' => 42,
            'title' => 'First entry',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'id' => 107,
            'user_id' => 42,
            'status' => 'read',
        ]);
        $this->assertDatabaseHas('recurring_transactions', [
            'id' => 108,
            'status' => 'paused',
            'active' => false,
        ]);
        $this->assertDatabaseHas('recurring_log', [
            'id' => 109,
            'period_key' => 'M:2026-11',
        ]);
        $this->assertDatabaseHas('wallets', [
            'user_id' => 42,
            'name' => 'Main wallet',
            'currency' => 'KES',
        ]);

        $this->assertSame(1, DB::connection('legacy_mysql')->table('income')->count());

        // A second run must recognize identical primary keys and never duplicate rows.
        $this->artisan('finance:import-legacy --execute --report=integration-legacy-import.json')
            ->assertExitCode(0);

        $this->assertDatabaseCount('income', 1);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('savings', 1);
        $this->assertDatabaseCount('debts', 1);
        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseCount('savings_goals', 1);
        $this->assertDatabaseCount('achievements', 1);
        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertDatabaseCount('recurring_log', 1);

        $report = json_decode(File::get($this->reportPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['tables']['income']['already_present']);
        $this->assertSame('1000.10', $report['financial_totals_by_user']['income']['source']['42']);
        $this->assertSame('1000.10', $report['financial_totals_by_user']['income']['target']['42']);
        $this->assertEquals(0.0, (float) $report['debt_outstanding_by_user']['source']['42']);
        $this->assertEquals(0.0, (float) $report['debt_outstanding_by_user']['target']['42']);
        $this->assertSame(0, $report['target_integrity']['duplicate_recurring_period_groups']);
        $this->assertSame(0, array_sum($report['target_integrity']['foreign_key_orphan_counts']));

        $walletId = DB::table('wallets')->where('user_id', 42)->where('is_default', true)->value('id');
        $nextIncomeId = DB::table('income')->insertGetId([
            'user_id' => 42,
            'wallet_id' => $walletId,
            'amount' => '1.00',
            'source' => 'Sequence verification',
            'transaction_date' => '2026-11-25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(101, $nextIncomeId);
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_mysql') && config('database.connections.legacy_mysql.database')) {
            foreach ($this->legacyTables as $table) {
                Schema::connection('legacy_mysql')->dropIfExists($table);
            }
        }

        if (isset($this->reportPath)) {
            File::delete($this->reportPath);
        }

        parent::tearDown();
    }

    private function configureStagingAllowlist(): void
    {
        $source = config('database.connections.legacy_mysql');
        $target = config('database.connections.pgsql');

        config([
            'legacy-import.allowed_source_hosts' => [strtolower($source['host'])],
            'legacy-import.allowed_source_databases' => [$source['database']],
            'legacy-import.allowed_target_hosts' => [strtolower($target['host'])],
            'legacy-import.allowed_target_databases' => [$target['database']],
        ]);

        $this->reportPath = storage_path('app/private/legacy-import/integration-legacy-import.json');
    }

    private function createLegacySchema(): void
    {
        $schema = Schema::connection('legacy_mysql');
        foreach ($this->legacyTables as $table) {
            $schema->dropIfExists($table);
        }

        $schema->create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('fullname', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('password', 255)->nullable();
            $table->integer('points')->default(0);
            $table->string('avatar', 255)->nullable();
        });

        $schema->create('income', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('source', 100)->nullable();
            $table->date('date')->nullable();
            $table->string('income_month', 7)->nullable();
            $table->string('tag', 50)->nullable();
        });

        $schema->create('expenses', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->date('date')->nullable();
            $table->string('expense_month', 7)->nullable();
            $table->string('tag', 50)->nullable();
        });

        $schema->create('budget', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('limit_amount', 10, 2)->nullable();
            $table->string('budget_month', 7);
            $table->date('date')->nullable();
        });

        $schema->create('savings_goals', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title', 100);
            $table->decimal('target_amount', 10, 2)->nullable();
            $table->decimal('current_amount', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('notes', 500)->nullable();
        });

        $schema->create('savings', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('goal', 100);
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('savings_month', 7);
        });

        $schema->create('debts', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('type', 16);
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('pending');
        });

        $schema->create('achievements', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('title', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->integer('points')->default(0);
            $table->dateTime('unlocked_at')->nullable();
        });

        $schema->create('notifications', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->text('message');
            $table->string('status', 16)->default('unread');
            $table->timestamp('created_at')->useCurrent();
            $table->string('type', 50)->default('general');
            $table->dateTime('read_at')->nullable();
        });

        $schema->create('recurring_transactions', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('category', 50)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('type', 16);
            $table->string('frequency', 16);
            $table->date('next_due')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('label', 150)->nullable();
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->boolean('active')->default(true);
        });

        $schema->create('recurring_log', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('recurring_id');
            $table->integer('user_id');
            $table->string('applied_month', 7);
            $table->timestamp('applied_at')->useCurrent();
        });
    }

    private function seedSyntheticLegacyRows(): void
    {
        $source = DB::connection('legacy_mysql');
        $now = now()->toDateTimeString();

        $source->table('users')->insert([
            'id' => 42,
            'fullname' => 'Synthetic Import User',
            'email' => 'legacy-import@example.test',
            'password' => password_hash('SyntheticPass-123', PASSWORD_BCRYPT),
            'points' => 7,
            'avatar' => 'avatars/synthetic.jpg',
        ]);
        $source->table('income')->insert([
            'id' => 100, 'user_id' => 42, 'amount' => '1000.10', 'source' => 'Salary',
            'date' => '2026-11-01', 'income_month' => '2026-11', 'tag' => 'work',
        ]);
        $source->table('expenses')->insert([
            'id' => 101, 'user_id' => 42, 'category' => 'Food', 'amount' => '100.10',
            'date' => '2026-11-02', 'expense_month' => '2026-11', 'tag' => 'lunch',
        ]);
        $source->table('budget')->insert([
            'id' => 102, 'user_id' => 42, 'category' => 'Food', 'amount' => '300.00',
            'limit_amount' => '500.00', 'budget_month' => '2026-11', 'date' => '2026-11-01',
        ]);
        $source->table('savings_goals')->insert([
            'id' => 103, 'user_id' => 42, 'title' => 'Emergency fund', 'target_amount' => '2000.00',
            'current_amount' => '500.00', 'due_date' => '2027-01-01', 'status' => 'active',
            'notes' => 'Synthetic goal',
        ]);
        $source->table('savings')->insert([
            'id' => 104, 'user_id' => 42, 'goal' => 'Emergency fund', 'amount' => '500.00',
            'date' => '2026-11-03', 'savings_month' => '2026-11',
        ]);
        $source->table('debts')->insert([
            'id' => 105, 'user_id' => 42, 'description' => 'Settled test loan', 'amount' => '250.00',
            'type' => 'borrowed', 'due_date' => '2026-11-10', 'status' => 'paid',
        ]);
        $source->table('achievements')->insert([
            'id' => 106, 'user_id' => 42, 'title' => 'First entry',
            'description' => 'Synthetic achievement', 'points' => 10, 'unlocked_at' => $now,
        ]);
        $source->table('notifications')->insert([
            'id' => 107, 'user_id' => 42, 'message' => 'Synthetic notification',
            'status' => 'read', 'created_at' => $now, 'type' => 'general', 'read_at' => $now,
        ]);
        $source->table('recurring_transactions')->insert([
            'id' => 108, 'user_id' => 42, 'category' => 'Utilities', 'amount' => '50.00',
            'type' => 'expense', 'frequency' => 'monthly', 'next_due' => '2026-12-01',
            'status' => 'inactive', 'label' => 'Synthetic utility', 'day_of_month' => 1, 'active' => 0,
        ]);
        $source->table('recurring_log')->insert([
            'id' => 109, 'recurring_id' => 108, 'user_id' => 42,
            'applied_month' => '2026-11', 'applied_at' => $now,
        ]);
    }
}
