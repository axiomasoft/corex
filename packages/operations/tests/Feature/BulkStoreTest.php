<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Time\Testing\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

final class BulkStoreTest extends TestCase
{
    private Connection $connection;

    private ContextFixture $contexts;

    private FrozenClock $clock;

    private SqlBulkOperationStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSqlite('api_budget_stub');
        $this->configureSqlite('api_tenant');
        $this->selectSchema('api_budget_stub', 'api_tenant');
        $this->connection = DB::connection('api_tenant');

        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], app(ApiSchemaRegistrar::class)->tenant()->getName());
        $this->contexts = $this->contexts();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
        $this->store = $this->store($this->connection, $this->clock);
    }

    protected function tearDown(): void
    {
        foreach (['api_budget_stub', 'api_tenant', 'api_pg_root', 'api_pg_tenant'] as $name) {
            DB::purge($name);
        }

        parent::tearDown();
    }

    public function test_same_submission_returns_one_operation_and_pending_polling_needs_no_notification(): void
    {
        $first = $this->store->submit($this->definition(id: 'bulk-1', submission: 'submission-1'));
        $replay = $this->store->submit($this->definition(id: 'bulk-retry-id', submission: 'submission-1'));
        $claimed = $this->store->claim($this->scope(), 'worker-1');

        self::assertSame('bulk-1', $first->definition->id);
        self::assertSame('bulk-1', $replay->definition->id);
        self::assertSame(BulkOperationStatus::Pending, $first->status);
        self::assertSame(1, $this->connection->table('sys_api_bulk_operations')->count());
        self::assertNotNull($claimed);
        self::assertSame('bulk-1', $claimed->definition->id);
        self::assertSame(BulkOperationStatus::Running, $claimed->status);
        self::assertSame(1, $claimed->attempt);
        self::assertSame(1, $claimed->revision);
    }

    public function test_state_transition_matrix_and_cancel_completion_fence(): void
    {
        $pending = $this->store->submit($this->definition(id: 'bulk-pending', submission: 'submission-pending'));
        $cancelled = $this->store->cancel($this->scope(), $pending->definition->id);
        $cancelReplay = $this->store->cancel($this->scope(), $pending->definition->id);

        self::assertSame(BulkOperationStatus::Cancelled, $cancelled->status);
        self::assertSame($cancelled->revision, $cancelReplay->revision);
        self::assertNull($cancelled->leaseOwner);

        $this->store->submit($this->definition(id: 'bulk-running', submission: 'submission-running'));
        $running = $this->store->claim($this->scope(), 'worker-1');
        self::assertNotNull($running);

        try {
            $this->store->save(
                $this->next($running, BulkOperationStatus::Cancelled),
                'worker-1',
                $running->revision,
            );
            self::fail('A running operation skipped the cancelling state.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('running -> cancelled', $exception->getMessage());
        }

        $cancelling = $this->store->cancel($this->scope(), $running->definition->id);
        self::assertSame(BulkOperationStatus::Cancelling, $cancelling->status);
        self::assertSame($running->revision + 1, $cancelling->revision);
        self::assertFalse($this->store->save(
            $this->next($running, BulkOperationStatus::Completed, resultRef: ['id' => 'result-stale']),
            'worker-1',
            $running->revision,
        ));
        self::assertTrue($this->store->save(
            $this->next($cancelling, BulkOperationStatus::Cancelled),
            'worker-1',
            $cancelling->revision,
        ));
        self::assertSame(
            BulkOperationStatus::Cancelled,
            $this->store->find($this->scope(), $running->definition->id)?->status,
        );

        $this->store->submit($this->definition(id: 'bulk-completed', submission: 'submission-completed'));
        $completing = $this->store->claim($this->scope(), 'worker-completed');
        self::assertNotNull($completing);
        self::assertTrue($this->store->save(
            $this->next($completing, BulkOperationStatus::Completed, resultRef: ['id' => 'result-1']),
            'worker-completed',
            $completing->revision,
        ));
        self::assertSame(
            BulkOperationStatus::Completed,
            $this->store->cancel($this->scope(), 'bulk-completed')->status,
        );

        $this->store->submit($this->definition(id: 'bulk-failed', submission: 'submission-failed'));
        $failing = $this->store->claim($this->scope(), 'worker-failed');
        self::assertNotNull($failing);
        self::assertTrue($this->store->save(
            $this->next(
                $failing,
                BulkOperationStatus::Failed,
                error: ['code' => 'bulk_reconciliation_required'],
            ),
            'worker-failed',
            $failing->revision,
        ));
        self::assertSame(
            BulkOperationStatus::Failed,
            $this->store->find($this->scope(), 'bulk-failed')?->status,
        );
    }

    public function test_expired_lease_takeover_and_stale_revision_are_rejected(): void
    {
        $this->store->submit($this->definition(id: 'bulk-takeover', submission: 'submission-takeover'));
        $first = $this->store->claim($this->scope(), 'worker-1');
        self::assertNotNull($first);
        $this->clock->advance(new DateInterval('PT31S'));

        self::assertFalse($this->store->save(
            $this->next($first, BulkOperationStatus::Running, checkpoint: ['offset' => 1]),
            'worker-1',
            $first->revision,
        ));

        $replacement = $this->store->claim($this->scope(), 'worker-2');
        self::assertNotNull($replacement);
        self::assertSame(2, $replacement->attempt);
        self::assertSame($first->revision + 1, $replacement->revision);
        self::assertSame('worker-2', $replacement->leaseOwner);

        $progress = $this->next($replacement, BulkOperationStatus::Running, checkpoint: ['offset' => 2]);
        self::assertTrue($this->store->save($progress, 'worker-2', $replacement->revision));
        self::assertFalse($this->store->save($progress, 'worker-2', $replacement->revision));

        $current = $this->store->find($this->scope(), 'bulk-takeover');
        self::assertNotNull($current);
        self::assertSame(['offset' => 2], JsonDocuments::map($current->checkpoint));
        self::assertTrue($this->store->save(
            $this->next($current, BulkOperationStatus::Completed, resultRef: ['id' => 'result-2']),
            'worker-2',
            $current->revision,
        ));
    }

    public function test_wrong_active_connection_and_unsafe_input_references_fail_closed(): void
    {
        $wrongScope = new DataScope(
            accountId: 'account-1',
            workspaceId: null,
            connectionIdentity: 'sqlite:foreign-tenant',
            actorId: 'actor-1',
            restoreEpoch: hash('sha256', 'bulk-restore-epoch'),
        );

        try {
            $this->store->submit($this->definition(
                id: 'bulk-foreign',
                submission: 'submission-foreign',
                scope: $wrongScope,
            ));
            self::fail('A foreign active connection unexpectedly admitted durable work.');
        } catch (ScopeContextMismatch $exception) {
            self::assertStringContainsString('foreign-tenant', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain URLs');
        $this->definition(
            id: 'bulk-url',
            submission: 'submission-url',
            inputRef: ['url' => 'https://example.invalid/input'],
        );
    }

    #[Group('pg')]
    public function test_postgresql_concurrent_submission_and_cancel_completion_race(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            self::markTestSkipped('The PostgreSQL bulk concurrency proof requires pcntl and Unix sockets.');
        }

        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

        $root = $this->configurePostgres($database);
        $root->statement('DROP SCHEMA IF EXISTS api_p146_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p146_tenant_test');

        try {
            $this->selectSchema('api_budget_stub', 'api_pg_tenant');

            $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], app(ApiSchemaRegistrar::class)->tenant()->getName());
            $scope = $this->scope(DB::connection('api_pg_tenant'));
            $submissions = $this->forkPostgresSubmissions($scope);

            self::assertSame($submissions[0], $submissions[1]);
            self::assertSame(1, DB::connection('api_pg_tenant')->table('sys_api_bulk_operations')->count());

            $store = $this->store(DB::connection('api_pg_tenant'), $this->clock);
            $store->cancel($scope, $submissions[0]);
            $store->submit($this->definition(
                id: 'bulk-pg-race',
                submission: 'submission-pg-race',
                scope: $scope,
            ));
            $claimed = $store->claim($scope, 'worker-pg');
            self::assertNotNull($claimed);
            $race = $this->forkPostgresCancelCompletion($scope, $claimed);
            $final = $store->find($scope, 'bulk-pg-race');

            self::assertNotNull($final);
            self::assertContains($final->status, [BulkOperationStatus::Completed, BulkOperationStatus::Cancelling]);

            if ($race['save']) {
                self::assertSame(BulkOperationStatus::Completed->value, $race['cancel']);
                self::assertSame(BulkOperationStatus::Completed, $final->status);
            } else {
                self::assertSame(BulkOperationStatus::Cancelling->value, $race['cancel']);
                self::assertSame(BulkOperationStatus::Cancelling, $final->status);
            }
        } finally {
            DB::disconnect('api_pg_tenant');
            $root->statement('DROP SCHEMA IF EXISTS api_p146_tenant_test CASCADE');
            DB::purge('api_pg_root');
        }
    }

    /** @return list<string> */
    private function forkPostgresSubmissions(DataScope $scope): array
    {
        $children = [];

        for ($index = 0; $index < 2; $index++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($pair);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                fclose($pair[0]);

                try {
                    DB::purge('api_pg_tenant');
                    $store = $this->store(
                        DB::connection('api_pg_tenant'),
                        new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00')),
                    );
                    fwrite($pair[1], "ready\n");
                    fgets($pair[1]);
                    $operation = $store->submit($this->definition(
                        id: "bulk-pg-{$index}",
                        submission: 'submission-pg',
                        scope: $scope,
                    ));
                    fwrite($pair[1], json_encode(['id' => $operation->definition->id], JSON_THROW_ON_ERROR)."\n");
                } catch (Throwable $exception) {
                    fwrite($pair[1], json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
                }

                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[] = ['pid' => $pid, 'socket' => $pair[0]];
        }

        return $this->releaseChildren($children, 'id');
    }

    /** @return array{save: bool, cancel: string} */
    private function forkPostgresCancelCompletion(DataScope $scope, BulkOperation $claimed): array
    {
        $children = [];

        foreach (['save', 'cancel'] as $action) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($pair);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                fclose($pair[0]);

                try {
                    DB::purge('api_pg_tenant');
                    $store = $this->store(
                        DB::connection('api_pg_tenant'),
                        new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00')),
                    );
                    fwrite($pair[1], "ready\n");
                    fgets($pair[1]);
                    $value = $action === 'save'
                        ? $store->save(
                            $this->next($claimed, BulkOperationStatus::Completed, resultRef: ['id' => 'pg-result']),
                            'worker-pg',
                            $claimed->revision,
                        )
                        : $store->cancel($scope, $claimed->definition->id)->status->value;
                    fwrite($pair[1], json_encode(['action' => $action, 'value' => $value], JSON_THROW_ON_ERROR)."\n");
                } catch (Throwable $exception) {
                    fwrite($pair[1], json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
                }

                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[] = ['pid' => $pid, 'socket' => $pair[0]];
        }

        $values = $this->releaseChildren($children, null);

        /** @var array{save: bool, cancel: string} $values */
        return $values;
    }

    /**
     * @param  list<array{pid: int, socket: resource}>  $children
     * @return array<array-key, mixed>
     */
    private function releaseChildren(array $children, ?string $valueKey): array
    {
        foreach ($children as $child) {
            self::assertSame("ready\n", fgets($child['socket']));
        }

        foreach ($children as $child) {
            fwrite($child['socket'], "go\n");
        }

        $values = [];

        foreach ($children as $child) {
            $result = json_decode((string) fgets($child['socket']), true, flags: JSON_THROW_ON_ERROR);
            fclose($child['socket']);
            pcntl_waitpid($child['pid'], $status);
            self::assertSame(0, pcntl_wexitstatus($status));
            self::assertArrayNotHasKey('error', $result, $result['error'] ?? 'PostgreSQL child failed.');

            if ($valueKey !== null) {
                $values[] = $result[$valueKey];

                continue;
            }

            $values[$result['action']] = $result['value'];
        }

        return $values;
    }

    private function store(Connection $connection, FrozenClock $clock): SqlBulkOperationStore
    {
        return new SqlBulkOperationStore(
            connection: $connection,
            scopeCodec: new ArrayDataScopeCodec,
            contexts: new GuardedTenantContextRestorer($this->contexts),
            clock: $clock,
            leaseSeconds: 30,
            resultRetentionSeconds: 86_400,
        );
    }

    private function contexts(): ContextFixture
    {
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));

        return $contexts;
    }

    private function scope(?Connection $connection = null): DataScope
    {
        $connection ??= $this->connection;

        return new DataScope(
            accountId: 'account-1',
            workspaceId: null,
            connectionIdentity: $connection->getDriverName().':'.$connection->getDatabaseName(),
            actorId: 'actor-1',
            restoreEpoch: hash('sha256', 'bulk-restore-epoch'),
        );
    }

    /** @param array<string, mixed>|null $inputRef */
    private function definition(
        string $id,
        string $submission,
        ?DataScope $scope = null,
        ?array $inputRef = null,
    ): BulkOperationDefinition {
        $scope ??= $this->scope();

        return new BulkOperationDefinition(
            id: $id,
            submissionId: $submission,
            operationId: 'notes.bulk-import',
            major: 'v1',
            contractHash: hash('sha256', 'bulk-contract'),
            encodedScope: (new ArrayDataScopeCodec)->encode($scope),
            actorId: $scope->actorId,
            clientId: 'client-1',
            extensionStamp: JsonDocuments::capture([
                'point' => 'api.operation',
                'id' => 'notes.bulk-import',
                'version' => '1',
                'registryFingerprint' => hash('sha256', 'registry'),
            ]),
            inputRef: JsonDocuments::capture($inputRef ?? ['kind' => 'parameters', 'parameters' => ['limit' => 10]]),
        );
    }

    /**
     * @param  array<string, mixed>|null  $checkpoint
     * @param  array<string, mixed>|null  $resultRef
     * @param  array<string, mixed>|null  $error
     */
    private function next(
        BulkOperation $operation,
        BulkOperationStatus $status,
        ?array $checkpoint = null,
        ?array $resultRef = null,
        ?array $error = null,
    ): BulkOperation {
        return new BulkOperation(
            definition: $operation->definition,
            transferRef: JsonDocuments::nullable(JsonDocuments::optionalMap($operation->transferRef)),
            status: $status,
            terminalStatus: $operation->terminalStatus,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: $operation->cancelRequestedAt,
            checkpoint: JsonDocuments::capture($checkpoint ?? JsonDocuments::map($operation->checkpoint)),
            progress: JsonDocuments::capture(JsonDocuments::map($operation->progress)),
            resultRef: JsonDocuments::nullable($resultRef),
            error: JsonDocuments::nullable($error),
            attempt: $operation->attempt,
            nextAttemptAt: $operation->nextAttemptAt,
            createdAt: $operation->createdAt,
            finishedAt: $operation->finishedAt,
            expiresAt: $operation->expiresAt,
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
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => [
                'connection' => $budget,
                'driver' => (string) $this->app['config']->get('database.connections.'.$budget.'.driver'),
                'database' => (string) $this->app['config']->get('database.connections.'.$budget.'.database'),
            ],
            'tenant' => [
                'connection' => $tenant,
                'driver' => (string) $this->app['config']->get('database.connections.'.$tenant.'.driver'),
                'database' => (string) $this->app['config']->get('database.connections.'.$tenant.'.database'),
            ],
        ]);
    }

    private function configurePostgres(string $database): Connection
    {
        $base = [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
        ];
        $this->app['config']->set('database.connections.api_pg_root', $base + ['search_path' => 'public']);
        $this->app['config']->set('database.connections.api_pg_tenant', $base + ['search_path' => 'api_p146_tenant_test']);
        DB::purge('api_pg_root');
        DB::purge('api_pg_tenant');

        $root = DB::connection('api_pg_root');
        self::assertSame($database, $root->getDatabaseName());

        return $root;
    }
}
