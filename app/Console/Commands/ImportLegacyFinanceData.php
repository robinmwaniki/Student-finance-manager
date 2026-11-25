<?php

namespace App\\Console\\Commands;

use App\\Services\\LegacyImport\\LegacyValueMapper;
use Illuminate\\Console\\Command;
use Illuminate\\Database\\Connection;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\File;
use Illuminate\\Support\\Facades\\Schema;
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

            $reportPath = $this->writePrivateReport($sourceName, $targetName, $execute);
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

        if (($source['driver'] ?? null) !== 'mysql' || ($target['driver'] ?? null) !== 'pgsql') {
            throw new InvalidArgumentException('The source must be MySQL/MariaDB and the target must be PostgreSQL.');
        }

        if ($sourceName === $targetName) {
            throw new InvalidArgumentException('Source and target connection names must differ.');
        }

        $targetDatabase = (string) ($target['database'] ?? '');
        $allowed = config('legacy-import.allowed_target_databases', []);

        if ($targetDatabase === '' || ! in_array($targetDatabase, $allowed, true)) {
            throw new InvalidArgumentException('Target database is not on LEGACY_IMPORT_ALLOWED_TARGET_DATABASES.');
        }

        if (preg_match('/(^|[_-])(prod|production)([_-]|$)/i', $targetDatabase)) {
            throw new InvalidArgumentException('Production-looking target database names are forbidden.');
        }

        $sourceIdentity = strtolower(($source['host'] ?? '').'/'.($source['database'] ?? ''));
        $targetIdentity = strtolower(($target['host'] ?? '').'/'.$targetDatabase);

        if ($sourceIdentity === $targetIdentity) {
            throw new InvalidArgumentException('Source and target database identities must differ.');
        }
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

                    try {
                        $record = $this->mapRecord($sourceTable, $row, $target, $mapper, $execute);
                        $this->assertReferences($sourceTable, $record, $target);

                        $existing = $target->table($targetTable)->where('id', $id)->first();
                        if ($existing) {
                            if ($this->sameBusinessRecord($existing, $record)) {
                                $this->counts[$sourceTable]['already_present']++;
                                $this->rememberImportedReference($sourceTable, $id, $record);
                                continue;
                            }

                            throw new InvalidArgumentException('Primary-key conflict with a different target record.');
                        }

                        if ($execute) {
                            $target->table($targetTable)->insert($record);
                        }

                        $this->counts[$sourceTable]['imported']++;
                        $this->rememberImportedReference($sourceTable, $id, $record);
                    } catch (Throwable $exception) {
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
                'points' => max(0, (int) ($row->points ?? 0)),
                'avatar' => $row->avatar ?? null,
                'email_verified_at' => null,
                'remember_token' => null,
                'is_active' => true,
                'theme' => null,
                'dashboard_preferences' => null,
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
                'points' => max(0, (int) ($row->points ?? 0)),
                'unlocked_at' => $row->unlocked_at ?? null,
            ] + $timestamps,
            'notifications' => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'message' => $this->requiredString($row->message ?? null, 'message'),
                'status' => in_array($row->status ?? 'unread', ['unread', 'read'], true)
                    ? ($row->status ?? 'unread') : throw new InvalidArgumentException('Notification status is not supported.'),
                'type' => $row->type ?? 'general',
                'created_at' => $row->created_at ?? $now,
                'updated_at' => null,
                'deduplication_key' => null,
                'read_at' => $row->read_at ?? null,
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
                'day_of_month' => min(31, max(1, (int) ($row->day_of_month ?? 1))),
                'active' => $mapper->recurringStatus($row->status ?? 'active')['active'] && $mapper->boolean($row->active ?? 1),
            ] + $timestamps,
            'recurring_log' => [
                'id' => (int) $row->id,
                'recurring_id' => (int) $row->recurring_id,
                'user_id' => (int) $row->user_id,
                'applied_month' => $mapper->month($row->applied_month ?? null),
                'period_key' => $mapper->recurringPeriodKey((string) $row->applied_month),
                'applied_at' => $row->applied_at ?? $now,
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
        $goal = $target->table('savings_goals')
            ->where('user_id', $userId)
            ->where('title', $title)
            ->orderBy('id')
            ->first();

        return $goal ? (int) $goal->id : null;
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

    private function rememberImportedReference(string $sourceTable, int $id, array $record): void
    {
        if ($sourceTable === 'users') {
            $this->knownUsers[$id] = true;
        }
        if ($sourceTable === 'recurring_transactions') {
            $this->knownRecurring[$id] = true;
        }
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

    private function writePrivateReport(string $sourceName, string $targetName, bool $execute): string
    {
        $directory = storage_path('app/private/legacy-import');
        File::ensureDirectoryExists($directory);

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
            'note' => 'Contains row IDs and financial reconciliation metadata. Keep this report private; it contains no names, emails, passwords or raw row payloads.',
        ];

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function tableSummary(): void
    {
        $this->table(['Legacy table', 'Source', 'Imported', 'Existing', 'Rejected'], collect($this->counts)->map(
            fn (array $count, string $table): array => [$table, $count['source'], $count['imported'], $count['already_present'], $count['rejected']]
        )->values()->all());
    }
}
