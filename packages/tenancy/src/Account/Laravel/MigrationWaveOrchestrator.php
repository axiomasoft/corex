<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Laravel;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Tenancy\Account\Internal\DatabaseValue;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Account\Internal\Models\AccountSchemaState;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Internal\Models\MigrationBatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs a `root_migration_batches` wave across its target accounts,
 * sequentially (B-11 §5.6, P2.12) — synchronous/injectable so it's testable
 * without a real queue worker. `-p`/`--skip-failing` (see
 * `TenantsMigrateCommand`) are accepted pass-through flags only: this
 * orchestrator already never aborts a batch on a single account's failure
 * (AC-11), and real parallel dispatch is Scope Excluded (B-15 DDL-worker
 * coordination — only the advisory-lock CALL itself is required, AC-12).
 *
 * Batch duration budgets / rate-limiting / `CREATE INDEX CONCURRENTLY`
 * guidance are operational concerns for the migration-file author, not
 * something this class enforces — deliberately out of scope.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class MigrationWaveOrchestrator
{
    /**
     * @param  Closure(AccountRef):void|null  $migrationRunner
     */
    public function __construct(
        private string $centralConnection,
        private TenancyManager $tenancy,
        private TemplateIntegrityVerifier $templateIntegrityVerifier,
        private SchemaMigrator $schema,
        private ?Closure $migrationRunner = null,
    ) {}

    public function runBatch(string $batchId, int $chunkSize = 5): void
    {
        /** @var MigrationBatch $batch */
        $batch = MigrationBatch::query()->findOrFail($batchId);

        $this->assertBatchIntegrity();

        $batch->forceFill(['status' => 'running', 'started_at' => $batch->started_at ?? now()])->save();

        foreach ($this->targetAccountStates($batch)->chunk($chunkSize) as $chunk) {
            /** @var Collection<int, AccountSchemaState> $chunk */
            foreach ($chunk as $state) {
                $this->migrateOne(state: $state, batch: $batch);
            }
        }

        $batch->forceFill(['status' => 'done', 'finished_at' => now()])->save();
    }

    private function assertBatchIntegrity(): void
    {
        Account::query()
            ->where('deleted_at', null)
            ->each(function (Account $account): void {
                $this->templateIntegrityVerifier->assertAccount($account);
            });
    }

    /**
     * @return Collection<int, AccountSchemaState>
     */
    private function targetAccountStates(MigrationBatch $batch): Collection
    {
        $accountsWithoutState = DB::connection($this->centralConnection)
            ->table('root_accounts')
            ->whereNull('deleted_at')
            ->whereNotIn('id', AccountSchemaState::query()->toBase()->select('account_id'))
            ->pluck('id');

        foreach ($accountsWithoutState as $accountId) {
            AccountSchemaState::query()->create([
                'account_id' => DatabaseValue::text($accountId),
                'current_version' => 0,
                'target_version' => $batch->to_version,
                'status' => 'ok',
                'batch_id' => $batch->id,
            ]);
        }

        AccountSchemaState::query()
            ->where('target_version', '<', $batch->to_version)
            ->update(['target_version' => $batch->to_version, 'batch_id' => $batch->id]);

        $query = AccountSchemaState::query()
            ->where('target_version', $batch->to_version)
            ->where('current_version', '<', $batch->to_version);
        $query->getQuery()->orderBy('account_id');

        return $query->get();
    }

    private function migrateOne(AccountSchemaState $state, MigrationBatch $batch): void
    {
        $wasAlreadyFailed = $state->status === 'failed';

        $state->forceFill(['status' => 'migrating'])->save();

        $accountRow = DB::connection($this->centralConnection)->table('root_accounts')->where('id', $state->account_id)->first();

        if ($accountRow === null) {
            $state->forceFill(['status' => $wasAlreadyFailed ? 'blocked' : 'failed', 'last_error' => 'account not found in root_accounts'])->save();

            return;
        }

        $accountRef = new AccountRef(
            id: DatabaseValue::text($accountRow->id),
            slug: DatabaseValue::text($accountRow->slug ?? ''),
            status: DatabaseValue::text($accountRow->status),
            features: [],
            limits: [],
        );

        try {
            $this->tenancy->runFor(account: $accountRef, workspace: null, callback: function () use ($accountRef): void {
                $runner = $this->migrationRunner ?? $this->defaultMigrationRunner();
                $runner($accountRef);
            });

            $state->forceFill([
                'current_version' => $batch->to_version,
                'status' => 'ok',
                'last_migrated_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $state->forceFill([
                'status' => $wasAlreadyFailed ? 'blocked' : 'failed',
                'last_error' => $e->getMessage(),
            ])->save();
        }
    }

    /**
     * @return Closure(AccountRef):void
     */
    private function defaultMigrationRunner(): Closure
    {
        // $account is not needed by the default runner itself (it acts on
        // whichever account TenancyManager::runFor() already switched the
        // 'tenant' connection to) — kept in the signature only so a
        // caller-supplied $migrationRunner can log/branch on it.
        return function (AccountRef $account): void {
            DB::connection('tenant')->transaction(function () use ($account): void {
                // AC-12 — the advisory-lock CALL is the actual requirement
                // here (B-15 DDL-worker coordination itself is Scope
                // Excluded); `pg_advisory_xact_lock` auto-releases at
                // transaction end.
                DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('fld_ddl')) /* account:{$account->id} */");

                $this->schema->migrate(scope: SchemaScope::Tenant, connection: 'tenant');
            });
        };
    }
}
