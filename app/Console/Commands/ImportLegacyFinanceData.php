<?php

namespace App\Console\Commands;

use App\Services\LegacyImport\LegacyValueMapper;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class ImportLegacyFinanceData extends Command
{
    protected $signature = 'finance:import-legacy
        {--source= : Named MySQL/MariaDB source connection}
        {--target= : Named PostgreSQL staging connection}
        {--execute : Apply inserts; without this flag the command is a dry run}
        {--report= : Private report filename (not a path)}';

    protected $description = 'Validate and import legacy finance records into an explicitly allowlisted staging database';

    private array $counts = [];
    private array $rejected = [];
    private array $knownUsers = [];
    private array $knownRecurring = [];
    private array $knownGoals = [];
    private array $conflictedUsers = [];
    private array $conflictedGoals = [];
    private int $unsafeAvatarPathsOmitted = 0;

    private const TABLES = [
        'users' => 'users',
        'income' => 'income',
        'expenses' => 'expenses',
        'budget' => 'budgets',
        'savings_goals' => 'savings_goals',
        'savings' => 'savings',
        'debts' => 'debts',
        'achievements' => 'achievements',
        'notifications' => 'user_notifications',
        'recurring_transactions' => 'recurring_transactions',
        'recurring_log' => 'recurring_log',
    ];

    public function handle(LegacyValueMapper $mapper): int
    {
        $this->counts = [];
        $this->rejected = [];
        $this->knownUsers = [];
        $this->knownRecurring = [];
        $this->knownGoals = [];
        $this->conflictedUsers = [];
        $this->conflictedGoals = [];
        $this->unsafeAvatarPathsOmitted = 0;

        $sourceName = $this->option('source') ?: config('legacy-import.source_connection');
        $targetName = $this->option('target') ?: config('legacy-import.target_connection');
        $execute = (bool) $this->option('execute');

        try {
            $sourceConfig = config("database.connections.{$sourceName}");
            $targetConfig = config("database.connections.{$targetName}");
            $this->assertSafeConnections($sourceName, $sourceConfig, $targetName, $targetConfig);

            $source = DB::connection($sourceName);
            $target = DB::connection($targetName);
            $this->assertSchemasExist($sourceName, $targetName);

            if ($execute) {
                $target->beginTransaction();
            }

            // MySQL applies this read-only mode to the next transaction. The
            // importer issues SELECT statements only against the source.
            $source->statement('SET TRANSACTION READ ONLY');
            $source->beginTransaction();

            try {
                foreach (array_keys(self::TABLES) as $sourceTable) {
                    $this->importTable($source, $target, $sourceTable, self::TABLES[$sourceTable], $mapper, $execute);
                }

                if ($execute) {
                    $this->resetIdentitySequences($target);
                    $target->commit();
                }
                $source->commit();
            } catch (Throwable $exception) {
                if ($execute && $target->transactionLevel() > 0) {
                    $target->rollBack();
                }
                if ($source->transactionLevel() > 0) {
                    $source->rollBack();
                }

                throw $exception;
            }

            $reportPath = $this->writePrivateReport($sourceName, $targetName, $execute, $source, $target);
            $this->tableSummary();
            $this->info(($execute ? 'Import transaction committed.' : 'Dry run completed; no target rows were written.'));
            $this->line('Private reconciliation report: '.$reportPath);

            return collect($this->counts)->sum('rejected') > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Legacy import stopped safely: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertSafeConnections(string $sourceName, ?array $source, string $targetName, ?array $target): void
    {
        if (! is_array($source) || ! is_array($target)) {
            throw new InvalidArgumentException('Both named database connections must exist.');
        }

        if (app()->environment() !== config('legacy-import.allowed_environment')) {
            throw new InvalidArgumentException('Imports are permitted only when APP_ENV is staging.');
        }

        if ($sourceName === $targetName) {
            throw new InvalidArgumentException('Source and target connection names must differ.');
        }

        $sourceDetails = $this->connectionDetails($source);
        $targetDetails = $this->connectionDetails($target);

        if ($sourceDetails['driver'] !== 'mysql' || $targetDetails['driver'] !== 'pgsql') {
            throw new InvalidArgumentException('The source must resolve to MySQL/MariaDB and the target to PostgreSQL.');
        }

        $allowedSourceHosts = array_map('strtolower', config('legacy-import.allowed_source_hosts', []));
        $allowedSourceDatabases = config('legacy-import.allowed_source_databases', []);
        $allowedTargetHosts = array_map('strtolower', config('legacy-import.allowed_target_hosts', []));
        $allowedTargetDatabases = config('legacy-import.allowed_target_databases', []);

        if ($sourceDetails['host'] === '' || ! in_array($sourceDetails['host'], $allowedSourceHosts, true)) {
            throw new InvalidArgumentException('Resolved source host is not on LEGACY_IMPORT_ALLOWED_SOURCE_HOSTS.');
        }
        if ($sourceDetails['database'] === '' || ! in_array($sourceDetails['database'], $allowedSourceDatabases, true)) {
            throw new InvalidArgumentException('Resolved source database is not on LEGACY_IMPORT_ALLOWED_SOURCE_DATABASES.');
        }
        if ($targetDetails['host'] === '' || ! in_array($targetDetails['host'], $allowedTargetHosts, true)) {
            throw new InvalidArgumentException('Resolved target host is not on LEGACY_IMPORT_ALLOWED_TARGET_HOSTS.');
        }
        $targetDatabase = $targetDetails['database'];
        if ($targetDatabase === '' || ! in_array($targetDatabase, $allowedTargetDatabases, true)) {
            throw new InvalidArgumentException('Resolved target database is not on LEGACY_IMPORT_ALLOWED_TARGET_DATABASES.');
        }

        if (preg_match('/(^|[_-])(prod|production)([_-]|$)/i', $sourceDetails['database'])) {
            throw new InvalidArgumentException('Production-looking source database names are forbidden.');
        }
        if (preg_match('/(^|[_-])(prod|production)([_-]|$)/i', $targetDatabase)) {
            throw new InvalidArgumentException('Production-looking target database names are forbidden.');
        }

        $sourceIdentity = strtolower($sourceDetails['host'].'/'.$sourceDetails['database']);
        $targetIdentity = strtolower($targetDetails['host'].'/'.$targetDatabase);
        if ($sourceIdentity === $targetIdentity) {
            throw new InvalidArgumentException('Source and target database identities must differ.');
        }
    }

    private function connectionDetails(array $config): array
    {
        $driver = (string) ($config['driver'] ?? '');
        $host = (string) ($config['host'] ?? '');
        $database = (string) ($config['database'] ?? '');
        $url = $config['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $parts = parse_url($url);
            if ($parts === false) {
                throw new InvalidArgumentException('A configured database URL could not be parsed safely.');
            }

            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $driver = match ($scheme) {
                'mysql', 'mariadb' => 'mysql',
                'pgsql', 'postgres', 'postgresql' => 'pgsql',
                default => $driver,
            };
            $host = strtolower((string) ($parts['host'] ?? $host));
            $urlDatabase = rawurldecode(trim((string) ($parts['path'] ?? ''), '/'));
            if ($urlDatabase !== '') {
                $database = $urlDatabase;
            }
        }

        return ['driver' => $driver, 'host' => strtolower($host), 'database' => $database];
    }

    private function assertSchemasExist(string $sourceName, string $targetName): void
    {
        foreach (self::TABLES as $sourceTable => $targetTable) {
            if (! Schema::connection($sourceName)->hasTable($sourceTable)) {
                throw new InvalidArgumentException("Required legacy source table is missing: {$sourceTable}.");
            }
            if (! Schema::connection($targetName)->hasTable($targetTable)) {
                throw new InvalidArgumentException("Required PostgreSQL target table is missing: {$targetTable}.");
            }
        }

        foreach (['wallets'] as $requiredTargetTable) {
            if (! Schema::connection($targetName)->hasTable($requiredTargetTable)) {
                throw new InvalidArgumentException("Required PostgreSQL target table is missing: {$requiredTargetTable}.");
            }
        }
    }

    private function importTable(
        Connection $source,
        Connection $target,
        string $sourceTable,
        string $targetTable,
        LegacyValueMapper $mapper,
        bool $execute
    ): void {
        $this->counts[$sourceTable] = ['source' => 0, 'imported' => 0, 'already_present' => 0, 'rejected' => 0];

        $source->table($sourceTable)->orderBy('id')->chunkById(
            (int) config('legacy-import.batch_size', 500),
            function ($rows) use ($source, $target, $sourceTable, $targetTable, $mapper, $execute): void {
                foreach ($rows as $row) {
                    $this->counts[$sourceTable]['source']++;
                    $id = (int) $row->id;

                    if ($execute) {
                        $target->beginTransaction(); // Per-row savepoint keeps one bad row from poisoning the batch.
                    }

                    try {
                        $record = $this->mapRecord($sourceTable, $row, $target, $mapper, $execute);
                        $this->assertReferences($sourceTable, $record, $target);

                        $existing = $target->table($targetTable)->where('id', $id)->first();
                        if ($existing) {
                            if ($this->sameBusinessRecord($existing, $record)) {
                                $this->counts[$sourceTable]['already_present']++;
                                $this->rememberImportedReference($sourceTable, $id, $record, $target, $execute);
                                if ($execute) {
                                    $target->commit();
                                }
                                continue;
                            }

                            throw new InvalidArgumentException('Primary-key conflict with a different target record.');
                        }

                        if ($execute) {
                            $target->table($targetTable)->insert($record);
                        }

                        $this->counts[$sourceTable]['imported']++;
                        $this->rememberImportedReference($sourceTable, $id, $record, $target, $execute);
                        if ($execute) {
                            $target->commit();
                        }
                    } catch (Throwable $exception) {
                        if ($sourceTable === 'users') {
                            $this->conflictedUsers[$id] = true;
                        }
                        if ($sourceTable === 'savings_goals' && isset($row->user_id, $row->title)) {
                            $this->conflictedGoals[(int) $row->user_id.'|'.(string) $row->title] = true;
                        }
                        if ($execute && $target->transactionLevel() > 1) {
                            $target->rollBack();
                        }
                        $this->counts[$sourceTable]['rejected']++;
                        $this->rejected[] = [
                            'table' => $sourceTable,
                            'row_id' => $id,
                            'user_id' => isset($row->user_id) ? (int) $row->user_id : null,
                            'reason' => $exception instanceof InvalidArgumentException
                                ? $exception->getMessage()
                                : 'Target constraint or database error; inspect staging schema and retry.',
                        ];
                    }
                }
            },
            'id'
        );
    }

    private function mapRecord(string $table, object $row, Connection $target, LegacyValueMapper $mapper, bool $execute): array
    {
        $now = now()->toDateTimeString();
        $timestamps = [
            'created_at' => $row->created_at ?? $now,
            'updated_at' => $row->updated_at ?? $now,
        ];

        return match ($table) {
            'users' => [
                'id' => (int) $row->id,
                'fullname' => $this->requiredString($row->fullname ?? null, 'fullname'),
                'email' => $this->requiredString($row->email ?? null, 'email'),
                'password' => $mapper->bcryptHash($row->password ?? null),
                'points' => $this->nonNegativeInteger($row->points ?? 0, 'points'),
                'avatar' => $this->safeAvatarPath($row->avatar ?? null),
            ] + $timestamps,
            'income' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'wallet_id' => $this->defaultWalletId($target, (int) $row->user_id, $execute),
                'amount' => $mapper->amount($row->amount ?? null),
                'source' => $this->requiredString($row->source ?? null, 'source'),
                'transaction_date' => $mapper->date($row->date ?? null),
                'tag' => $row->tag ?? null,
                'submission_token' => null,
                'lock_version' => 0,
            ] + $timestamps,
            'expenses' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'wallet_id' => $this->defaultWalletId($target, (int) $row->user_id, $execute),
                'category' => $this->requiredString($row->category ?? null, 'category'),
                'amount' => $mapper->amount($row->amount ?? null),
                'transaction_date' => $mapper->date($row->date ?? null),
                'tag' => $row->tag ?? null,
                'submission_token' => null,
                'lock_version' => 0,
                'original_amount' => null,
                'original_currency' => null,
                'exchange_rate' => null,
                'converted_amount' => null,
                'exchange_rate_updated_at' => null,
                'exchange_rate_source' => null,
            ] + $timestamps,
            'budget' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'category' => $this->requiredString($row->category ?? null, 'category'),
                'amount' => $mapper->amount($row->amount ?? null),
                'limit_amount' => isset($row->limit_amount) ? $mapper->amount($row->limit_amount) : null,
                'budget_month' => $mapper->month($row->budget_month ?? null, $row->date ?? null),
                'date' => $mapper->date($row->date ?? null, true),
                'submission_token' => null,
                'lock_version' => 0,
            ] + $timestamps,
            'savings_goals' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'title' => $this->requiredString($row->title ?? null, 'title'),
                'target_amount' => isset($row->target_amount) ? $mapper->amount($row->target_amount) : null,
                'current_amount' => $mapper->amount($row->current_amount ?? '0'),
                'due_date' => $mapper->date($row->due_date ?? null, true),
                'status' => in_array($row->status ?? 'active', ['active', 'completed'], true)
                    ? ($row->status ?? 'active') : throw new InvalidArgumentException('Savings goal status is not supported.'),
                'notes' => $row->notes ?? null,
                'lock_version' => 0,
            ] + $timestamps,
            'savings' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'goal_id' => $this->matchingGoalId($target, (int) $row->user_id, (string) $row->goal),
                'goal' => $this->requiredString($row->goal ?? null, 'goal'),
                'amount' => $mapper->amount($row->amount ?? null),
                'transaction_date' => $mapper->date($row->date ?? null),
                'savings_month' => $mapper->month($row->savings_month ?? null, $row->date ?? null),
                'submission_token' => null,
                'lock_version' => 0,
            ] + $timestamps,
            'debts' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'description' => $row->description ?? null,
                'amount' => $mapper->amount($row->amount ?? null),
                'type' => $mapper->debtType($row->type ?? null),
                'due_date' => $mapper->date($row->due_date ?? null, true),
                'status' => in_array($row->status ?? 'pending', ['pending', 'paid'], true)
                    ? ($row->status ?? 'pending') : throw new InvalidArgumentException('Debt status is not supported.'),
                'submission_token' => null,
                'lock_version' => 0,
            ] + $timestamps,
            'achievements' => [
                'id' => (int) $row->id,
                'user_id' => isset($row->user_id) ? (int) $row->user_id : null,
                'code' => null,
                'title' => $row->title ?? null,
                'description' => $row->description ?? null,
                'points' => $this->nonNegativeInteger($row->points ?? 0, 'points'),
                'unlocked_at' => $mapper->dateTime($row->unlocked_at ?? null, true),
            ] + $timestamps,
            'notifications' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'message' => $this->requiredString($row->message ?? null, 'message'),
                'status' => in_array($row->status ?? 'unread', ['unread', 'read'], true)
                    ? ($row->status ?? 'unread') : throw new InvalidArgumentException('Notification status is not supported.'),
                'type' => $row->type ?? 'general',
                'created_at' => $mapper->dateTime($row->created_at ?? null) ?? $now,
                'updated_at' => null,
                'deduplication_key' => null,
                'read_at' => $mapper->dateTime($row->read_at ?? null, true),
            ],
            'recurring_transactions' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'category' => $row->category ?? null,
                'amount' => $mapper->amount($row->amount ?? null),
                'type' => in_array($row->type ?? null, ['income', 'expense'], true)
                    ? $row->type : throw new InvalidArgumentException('Recurring transaction type is not supported.'),
                'frequency' => in_array($row->frequency ?? null, ['daily', 'weekly', 'monthly'], true)
                    ? $row->frequency : throw new InvalidArgumentException('Recurring frequency is not supported.'),
                'next_due' => $mapper->date($row->next_due ?? null, true),
                'status' => $mapper->recurringStatus($row->status ?? 'active')['status'],
                'label' => $row->label ?? null,
                'day_of_month' => $this->dayOfMonth($row->day_of_month ?? 1),
                'active' => $mapper->recurringStatus($row->status ?? 'active')['active'] && $mapper->boolean($row->active ?? 1),
            ] + $timestamps,
            'recurring_log' => [
                'id' => (int) $row->id,
                'recurring_id' => (int) $row->recurring_id,
                'user_id' => (int) $row->user_id,
                'applied_month' => $mapper->month($row->applied_month ?? null),
                'period_key' => $mapper->recurringPeriodKey((string) $row->applied_month),
                'applied_at' => $mapper->dateTime($row->applied_at ?? null) ?? $now,
                'income_id' => null,
                'expense_id' => null,
            ],
            default => throw new InvalidArgumentException('No mapping exists for this legacy table.'),
        };
    }

    private function assertReferences(string $sourceTable, array $record, Connection $target): void
    {
        if (isset($record['user_id'])) {
            $userId = (int) $record['user_id'];
            if (isset($this->conflictedUsers[$userId])) {
                throw new InvalidArgumentException('Owner row was rejected; dependent records cannot be attached to an unverified account.');
            }
            if (! isset($this->knownUsers[$userId]) && ! $target->table('users')->where('id', $userId)->exists()) {
                throw new InvalidArgumentException('Owner reference does not exist in the target or imported users.');
            }
        }

        if ($sourceTable === 'recurring_log') {
            $recurringId = (int) $record['recurring_id'];
            if (! isset($this->knownRecurring[$recurringId]) && ! $target->table('recurring_transactions')->where('id', $recurringId)->exists()) {
                throw new InvalidArgumentException('Recurring transaction reference does not exist.');
            }
        }
    }

    private function defaultWalletId(Connection $target, int $userId, bool $execute): ?int
    {
        $wallet = $target->table('wallets')->where('user_id', $userId)->where('name', 'Main wallet')->first();
        if ($wallet) {
            return (int) $wallet->id;
        }
        if (! $execute) {
            return null;
        }

        return (int) $target->table('wallets')->insertGetId([
            'user_id' => $userId,
            'name' => 'Main wallet',
            'type' => 'cash',
            'currency' => 'KES',
            'opening_balance' => '0.00',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function matchingGoalId(Connection $target, int $userId, string $title): ?int
    {
        $key = $userId.'|'.$title;
        if (isset($this->conflictedGoals[$key])) {
            throw new InvalidArgumentException('Matching savings goal was rejected; contribution cannot be linked safely.');
        }
        if (isset($this->knownGoals[$key])) {
            if (count($this->knownGoals[$key]) > 1) {
                throw new InvalidArgumentException('Savings goal title matches multiple imported goals; manual mapping is required.');
            }

            return (int) $this->knownGoals[$key][0];
        }

        $matches = $target->table('savings_goals')
            ->where('user_id', $userId)
            ->where('title', $title)
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw new InvalidArgumentException('Savings goal title matches multiple target goals; manual mapping is required.');
        }

        return $matches->first() ? (int) $matches->first()->id : null;
    }

    private function sameBusinessRecord(object $existing, array $record): bool
    {
        foreach ($record as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at'], true)) {
                continue;
            }

            $current = $existing->{$key} ?? null;
            if ($current === null && $value === null) {
                continue;
            }
            if ((string) $current !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function rememberImportedReference(string $sourceTable, int $id, array $record, Connection $target, bool $execute): void
    {
        if ($sourceTable === 'users') {
            $this->knownUsers[$id] = true;
            // User model events create this wallet for newly registered accounts.
            // Direct, ID-preserving imports bypass Eloquent events, so reproduce it explicitly.
            if ($execute) {
                $this->defaultWalletId($target, $id, true);
            }
        }
        if ($sourceTable === 'recurring_transactions') {
            $this->knownRecurring[$id] = true;
        }
        if ($sourceTable === 'savings_goals') {
            $key = (int) $record['user_id'].'|'.$record['title'];
            $this->knownGoals[$key][] = $id;
        }
    }

    private function safeAvatarPath(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $path = trim($value);
        if (str_starts_with($path, '/') || str_contains($path, '\\\\') || preg_match('/(^|\\/)\\.\\.(\\/|$)/', $path) || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
            $this->unsafeAvatarPathsOmitted++;

            return null;
        }

        return $path;
    }

    private function nonNegativeInteger(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (string) (int) $value !== (string) $value || (int) $value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative integer.");
        }

        return (int) $value;
    }

    private function dayOfMonth(mixed $value): int
    {
        $day = $this->nonNegativeInteger($value, 'day_of_month');
        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException('Recurring day_of_month must be between 1 and 31.');
        }

        return $day;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Required field {$field} is empty.");
        }

        return trim($value);
    }

    private function resetIdentitySequences(Connection $target): void
    {
        foreach (array_unique(array_values(self::TABLES)) as $table) {
            $target->statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1), EXISTS (SELECT 1 FROM \"{$table}\"))"
            );
        }
    }

    private function writePrivateReport(string $sourceName, string $targetName, bool $execute, Connection $source, Connection $target): string
    {
        $directory = storage_path('app/private/legacy-import');
        File::ensureDirectoryExists($directory, 0700, true);

        $filename = $this->option('report') ?: 'legacy-import-'.now()->format('Ymd-His').'.json';
        $filename = basename($filename);
        if (! str_ends_with($filename, '.json')) {
            $filename .= '.json';
        }

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $execute ? 'execute' : 'dry-run',
            'source_connection' => $sourceName,
            'target_connection' => $targetName,
            'tables' => $this->counts,
            'rejected_rows' => $this->rejected,
            'unsafe_avatar_paths_omitted' => $this->unsafeAvatarPathsOmitted,
            'financial_totals_by_user' => $this->financialTotalsByUser($source, $target),
            'monthly_category_totals' => $this->monthlyCategoryTotals($source, $target),
            'target_integrity' => $this->targetIntegritySummary($target),
            'note' => 'Contains row IDs and financial reconciliation metadata. Keep this report private; it contains no names, emails, passwords or raw row payloads.',
        ];

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        @chmod($path, 0600);

        return $path;
    }

    private function financialTotalsByUser(Connection $source, Connection $target): array
    {
        $totals = [];

        foreach ([
            'income' => 'income',
            'expenses' => 'expenses',
            'savings' => 'savings',
            'debts' => 'debts',
            'budget' => 'budgets',
        ] as $sourceTable => $targetTable) {
            $sourceRows = $source->table($sourceTable)
                ->select('user_id')
                ->selectRaw('CAST(SUM(amount) AS CHAR) AS total')
                ->groupBy('user_id')
                ->orderBy('user_id')
                ->get();

            $targetRows = $target->table($targetTable)
                ->select('user_id')
                ->selectRaw('CAST(SUM(amount) AS TEXT) AS total')
                ->groupBy('user_id')
                ->orderBy('user_id')
                ->get();

            $totals[$sourceTable] = [
                'source' => $sourceRows->mapWithKeys(fn (object $row): array => [(string) $row->user_id => (string) $row->total])->all(),
                'target' => $targetRows->mapWithKeys(fn (object $row): array => [(string) $row->user_id => (string) $row->total])->all(),
            ];
        }

        return $totals;
    }

    private function monthlyCategoryTotals(Connection $source, Connection $target): array
    {
        $specifications = [
            'income' => ['source' => 'income', 'target' => 'income', 'source_period' => "DATE_FORMAT(date, '%Y-%m')", 'target_period' => "TO_CHAR(transaction_date, 'YYYY-MM')", 'source_category' => 'source', 'target_category' => 'source'],
            'expenses' => ['source' => 'expenses', 'target' => 'expenses', 'source_period' => "DATE_FORMAT(date, '%Y-%m')", 'target_period' => "TO_CHAR(transaction_date, 'YYYY-MM')", 'source_category' => 'category', 'target_category' => 'category'],
            'savings' => ['source' => 'savings', 'target' => 'savings', 'source_period' => 'savings_month', 'target_period' => 'savings_month', 'source_category' => 'goal', 'target_category' => 'goal'],
            'budgets' => ['source' => 'budget', 'target' => 'budgets', 'source_period' => 'budget_month', 'target_period' => 'budget_month', 'source_category' => 'category', 'target_category' => 'category'],
        ];

        $result = [];
        foreach ($specifications as $name => $specification) {
            $sourceRows = $source->table($specification['source'])
                ->select('user_id')
                ->selectRaw($specification['source_period'].' AS period_key')
                ->addSelect($specification['source_category'])
                ->selectRaw('CAST(SUM(amount) AS CHAR) AS total')
                ->groupBy('user_id')
                ->groupByRaw($specification['source_period'])
                ->groupBy($specification['source_category'])
                ->get();

            $targetRows = $target->table($specification['target'])
                ->select('user_id')
                ->selectRaw($specification['target_period'].' AS period_key')
                ->addSelect($specification['target_category'])
                ->selectRaw('CAST(SUM(amount) AS TEXT) AS total')
                ->groupBy('user_id')
                ->groupByRaw($specification['target_period'])
                ->groupBy($specification['target_category'])
                ->get();

            $toMap = fn ($rows, string $category): array => $rows->mapWithKeys(
                fn (object $row): array => [
                    (string) $row->user_id.'|'.(string) $row->period_key.'|'.(string) ($row->{$category} ?? '') => (string) $row->total,
                ]
            )->all();

            $result[$name] = [
                'source' => $toMap($sourceRows, $specification['source_category']),
                'target' => $toMap($targetRows, $specification['target_category']),
            ];
        }

        return $result;
    }

    private function targetIntegritySummary(Connection $target): array
    {
        $orphans = [
            'income_user' => $target->table('income as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'expenses_user' => $target->table('expenses as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'savings_user' => $target->table('savings as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'debts_user' => $target->table('debts as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'budgets_user' => $target->table('budgets as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'savings_goals_user' => $target->table('savings_goals as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'user_notifications_user' => $target->table('user_notifications as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'recurring_transactions_user' => $target->table('recurring_transactions as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'achievements_user' => $target->table('achievements as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNotNull('records.user_id')->whereNull('users.id')->count(),
            'recurring_log_user' => $target->table('recurring_log as records')->leftJoin('users', 'users.id', '=', 'records.user_id')->whereNull('users.id')->count(),
            'recurring_log_schedule' => $target->table('recurring_log as records')->leftJoin('recurring_transactions', 'recurring_transactions.id', '=', 'records.recurring_id')->whereNull('recurring_transactions.id')->count(),
        ];

        $duplicateRecurringPeriods = $target->table('recurring_log')
            ->select('recurring_id', 'period_key')
            ->groupBy('recurring_id', 'period_key')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return [
            'foreign_key_orphan_counts' => $orphans,
            'duplicate_recurring_period_groups' => $duplicateRecurringPeriods,
        ];
    }

    private function tableSummary(): void
    {
        $this->table(['Legacy table', 'Source', 'Imported', 'Existing', 'Rejected'], collect($this->counts)->map(
            fn (array $count, string $table): array => [$table, $count['source'], $count['imported'], $count['already_present'], $count['rejected']]
        )->values()->all());
    }
}
