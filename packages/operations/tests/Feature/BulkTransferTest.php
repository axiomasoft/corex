<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\GuardedTenantContextRestorer;
use CoreX\Data\ArrayDataScopeCodec;
use CoreX\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Internal\BulkWorker;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Bulk\Internal\TransferBulkExecutor;
use CoreX\Operations\Bulk\Internal\TransferCapabilityUnavailable;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\Fixtures\BulkTransferFactory;
use CoreX\Operations\Tests\TestCase;
use CoreX\Time\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BulkTransferTest extends TestCase
{
    private Connection $connection;

    private FrozenClock $clock;

    private SqlBulkOperationStore $store;

    private DataScope $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSqlite('api_budget_stub');
        $this->configureSqlite('api_tenant');
        $this->selectSchema('api_budget_stub', 'api_tenant');
        $this->connection = DB::connection('api_tenant');
        $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_bulk_operations_table.php';
        $migration->up();
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
        $this->store = new SqlBulkOperationStore(
            connection: $this->connection,
            scopeCodec: new ArrayDataScopeCodec,
            contexts: new GuardedTenantContextRestorer($contexts),
            clock: $this->clock,
        );
        $this->scope = new DataScope(
            accountId: 'account-1',
            workspaceId: null,
            connectionIdentity: $this->connection->getDriverName().':'.$this->connection->getDatabaseName(),
            actorId: 'actor-1',
            restoreEpoch: hash('sha256', 'bulk-restore-epoch'),
        );
    }

    public function test_missing_factory_and_package_fail_before_submit(): void
    {
        $missingFactory = new TransferBulkExecutor(null, $this->store, $this->clock, scopes: new ArrayDataScopeCodec);
        $missingPackage = new TransferBulkExecutor(new BulkTransferFactory, $this->store, $this->clock, transferInstalled: false, scopes: new ArrayDataScopeCodec);

        foreach ([$missingFactory, $missingPackage] as $executor) {
            try {
                $executor->submit($this->definition('bulk-missing', 'submission-missing'));
                self::fail('Transfer submit proceeded without the capability.');
            } catch (TransferCapabilityUnavailable $exception) {
                self::assertSame('capability_unavailable', $exception->errorDescriptor()->code);
            }
        }

        self::assertSame(0, $this->connection->table('sys_api_bulk_operations')->count());
    }

    public function test_import_resume_does_not_repeat_sink_effects_around_checkpoint_crashes(): void
    {
        $factory = new BulkTransferFactory;
        $factory->crashBeforeCheckpoint = true;
        $worker = $this->worker($factory);
        $this->submit($worker);

        try {
            $worker->workOnce($this->scope);
            self::fail('The crash before checkpoint was swallowed.');
        } catch (RuntimeException $exception) {
            self::assertSame('crash before checkpoint', $exception->getMessage());
        }

        self::assertSame(0, $factory->sinkWrites);
        $this->clock->advance(new DateInterval('PT31S'));
        $resumed = $worker->workOnce($this->scope);

        self::assertNotNull($resumed);
        self::assertSame(1, $factory->sinkWrites);
        self::assertSame('1', $resumed->checkpoint['sequence']);

        $this->clock->advance(new DateInterval('PT31S'));
        $again = $worker->workOnce($this->scope);

        self::assertNotNull($again);
        self::assertSame(1, $factory->sinkWrites);
        self::assertSame('1', $again->checkpoint['sequence']);
    }

    public function test_repeat_create_returns_the_current_token_and_export_rotates_an_unsealed_attempt(): void
    {
        $factory = new BulkTransferFactory;
        $submitted = (new TransferBulkExecutor($factory, $this->store, $this->clock, scopes: new ArrayDataScopeCodec))
            ->submit($this->definition('bulk-export', 'submission-export', 'export'));
        $first = $factory->create($submitted);
        $second = $factory->create($submitted);

        self::assertSame($first->transfer->id, $second->transfer->id);

        $unsealed = new BulkOperation(
            definition: $submitted->definition,
            transferRef: null,
            status: BulkOperationStatus::Running,
            terminalStatus: null,
            revision: 1,
            leaseOwner: 'worker-1',
            leaseUntil: $this->clock->now()->add(new DateInterval('PT30S')),
            cancelRequestedAt: null,
            checkpoint: ['attempt_id' => 'old-attempt', 'sealed' => false, 'sequence' => '0'],
            progress: ['phase' => 'running'],
            resultRef: null,
            error: null,
            attempt: 1,
            nextAttemptAt: $this->clock->now(),
            createdAt: $this->clock->now(),
            finishedAt: null,
            expiresAt: null,
        );
        $factory->tokens = [];
        $rotated = $factory->create($unsealed);

        self::assertSame('old-attempt-retry', $rotated->manifest?->id);
        self::assertNotSame('old-attempt', $rotated->transfer->id);
    }

    public function test_stamp_drift_fails_before_the_next_batch_and_observers_cannot_change_outcomes(): void
    {
        $factory = new BulkTransferFactory;
        $factory->drift = ['restore_epoch' => hash('sha256', 'other-epoch')];
        $worker = $this->worker($factory);
        $this->submit($worker);
        $observed = [];
        $failed = $worker->workOnce($this->scope, function (string $phase, array $metrics) use (&$observed): void {
            $observed[] = [$phase, $metrics];

            throw new RuntimeException('observer exploded');
        });

        self::assertNotNull($failed);
        self::assertSame(BulkOperationStatus::Failed, $failed->status);
        self::assertSame('contract_drift', $failed->error['code'] ?? null);
        self::assertSame(0, $factory->steps);
        self::assertSame('started', $observed[0][0]);
        self::assertSame('failed', $observed[1][0]);
        self::assertArrayNotHasKey('secret', $observed[0][1]);
        self::assertSame(0, substr_count(file_get_contents(__DIR__.'/../../src/Bulk/Internal/TransferBulkExecutor.php') ?: '', 'CoreX\\Transfer\\Internal'));
        self::assertSame(0, substr_count(file_get_contents(__DIR__.'/../../src/Bulk/Internal/TransferBulkExecutor.php') ?: '', 'corex_transfer'));
    }

    private function worker(BulkTransferFactory $factory): BulkWorker
    {
        return new BulkWorker(
            store: $this->store,
            executor: new TransferBulkExecutor($factory, $this->store, $this->clock, scopes: new ArrayDataScopeCodec),
            clock: $this->clock,
            worker: 'worker-1',
        );
    }

    private function submit(BulkWorker $worker): void
    {
        $executor = new TransferBulkExecutor(
            factory: new BulkTransferFactory,
            store: $this->store,
            clock: $this->clock,
            scopes: new ArrayDataScopeCodec,
        );
        $executor->submit($this->definition('bulk-import', 'submission-import'));
        unset($worker);
    }

    private function definition(string $id, string $submission, string $kind = 'import'): BulkOperationDefinition
    {
        return new BulkOperationDefinition(
            id: $id,
            submissionId: $submission,
            operationId: 'notes.bulk-import',
            major: 'v1',
            contractHash: hash('sha256', 'bulk-contract'),
            encodedScope: (new ArrayDataScopeCodec)->encode($this->scope),
            actorId: 'actor-1',
            clientId: 'client-1',
            extensionStamp: [
                'point' => 'api.operation',
                'id' => 'notes.bulk-import',
                'version' => '1',
                'rights' => 'read',
                'restore_epoch' => hash('sha256', 'bulk-restore-epoch'),
                'registryFingerprint' => hash('sha256', 'registry'),
            ],
            inputRef: ['kind' => $kind, 'format' => 'ndjson', 'sha256' => hash('sha256', $kind), 'length' => 1],
        );
    }

    private function configureSqlite(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(string $budget, string $tenant): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => [
                'connection' => $budget,
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            'tenant' => [
                'connection' => $tenant,
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);
    }
}
