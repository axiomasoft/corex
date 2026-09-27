<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\Data\BulkOperation;
use CoreX\Api\Data\BulkOperationDefinition;
use CoreX\Api\Enums\BulkOperationStatus;
use CoreX\Api\Internal\Bulk\BulkFailureReader;
use CoreX\Api\Internal\Bulk\BulkOperations;
use CoreX\Api\Internal\Bulk\BulkResultReader;
use CoreX\Api\Internal\Bulk\SqlBulkOperationStore;
use CoreX\Api\Internal\Bulk\TransferBulkExecutor;
use CoreX\Api\Laravel\ApiSchemaRegistrar;
use CoreX\Api\Tests\Fixtures\BulkTransferFactory;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Support\Data\ArrayDataScopeCodec;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\GuardedTenantContextRestorer;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BulkResultAccessTest extends TestCase
{
    private SqlBulkOperationStore $store;

    private DataScope $scope;

    private int $manifestReads = 0;

    private int $failureReads = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('database.connections.api_budget_stub', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        $this->app['config']->set('database.connections.api_tenant', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('api_budget_stub');
        DB::purge('api_tenant');
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-api.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $connection = DB::connection('api_tenant');
        (require __DIR__.'/../../database/migrations/tenant/create_sys_api_bulk_operations_table.php')->up();
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
        $this->store = new SqlBulkOperationStore($connection, new ArrayDataScopeCodec, new GuardedTenantContextRestorer($contexts), $clock);
        $this->scope = new DataScope('account-1', null, $connection->getDriverName().':'.$connection->getDatabaseName(), 'actor-1', hash('sha256', 'epoch'));
        $this->clock = $clock;
    }

    private FrozenClock $clock;

    public function test_foreign_caller_and_revoked_field_access_do_not_read_the_result(): void
    {
        $api = $this->api(fieldAccess: static fn (): bool => false);
        $api->submit($this->scope, 'client-1', 'submission-1', $this->definition());
        $claimed = $this->store->claim($this->scope, 'worker-1');
        self::assertNotNull($claimed);
        self::assertTrue($this->store->save($this->completed($claimed), 'worker-1', $claimed->revision));

        $foreign = new DataScope('account-9', null, 'other:db', 'actor-9', hash('sha256', 'other'));
        self::assertSame(404, $api->result($foreign, 'client-9', 'bulk-1')->status());
        self::assertSame(404, $api->show($this->scope, 'client-9', 'bulk-1')->status());
        self::assertSame(404, $api->result($this->scope, 'client-1', 'bulk-1')->status());
        self::assertSame(0, $this->manifestReads);
        self::assertSame(0, $this->failureReads);
    }

    public function test_failure_cursor_rejects_limits_above_100(): void
    {
        $api = $this->api(fieldAccess: static fn (): bool => true);
        $api->submit($this->scope, 'client-1', 'submission-1', $this->definition());
        $page = $api->failurePage($this->scope, 'client-1', 'bulk-1', '10', 100);
        $denied = $api->failurePage($this->scope, 'client-1', 'bulk-1', null, 101);

        self::assertSame(200, $page->status());
        self::assertSame(422, $denied->status());
        self::assertSame(1, $this->failureReads);
        self::assertSame('limit_exceeded', $denied->getData(true)['code']);
    }

    private function api(callable $fieldAccess): BulkOperations
    {
        return new BulkOperations(
            $this->store,
            new TransferBulkExecutor(new BulkTransferFactory, $this->store, $this->clock, scopes: new ArrayDataScopeCodec),
            new BulkResultReader($fieldAccess, function (): array {
                $this->manifestReads++;

                return ['media_type' => 'application/json'];
            }),
            new BulkFailureReader(function (DataScope $scope, BulkOperation $operation, ?string $after, int $limit): array {
                if ($limit > BulkFailureReader::MAX_LIMIT) {
                    throw new InvalidArgumentException('limit');
                }
                $this->failureReads++;

                return ['records' => [], 'next' => $after];
            }),
            $this->clock,
        );
    }

    private function definition(): BulkOperationDefinition
    {
        return new BulkOperationDefinition(
            id: 'bulk-1',
            submissionId: 'submission-1',
            operationId: 'notes.bulk-import',
            major: 'v1',
            contractHash: hash('sha256', 'bulk-contract'),
            encodedScope: (new ArrayDataScopeCodec)->encode($this->scope),
            actorId: 'actor-1',
            clientId: 'client-1',
            extensionStamp: ['point' => 'api.operation', 'id' => 'notes.bulk-import', 'version' => '1'],
            inputRef: ['kind' => 'parameters', 'parameters' => ['limit' => 10]],
        );
    }

    private function completed(BulkOperation $operation): BulkOperation
    {
        return new BulkOperation(
            definition: $operation->definition,
            transferRef: null,
            status: BulkOperationStatus::Completed,
            terminalStatus: null,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: null,
            checkpoint: ['sequence' => '1'],
            progress: ['phase' => 'completed'],
            resultRef: ['id' => 'result-1'],
            error: null,
            attempt: $operation->attempt,
            nextAttemptAt: $operation->nextAttemptAt,
            createdAt: $operation->createdAt,
            finishedAt: null,
            expiresAt: null,
        );
    }
}
