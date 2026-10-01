<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Internal\BulkFailureReader;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use CoreX\Operations\Bulk\Internal\BulkResultReader;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Bulk\Internal\TransferBulkExecutor;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\Fixtures\BulkTransferFactory;
use CoreX\Operations\Tests\TestCase;
use CoreX\Time\Testing\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class BulkApiTest extends TestCase
{
    private FrozenClock $clock;

    private SqlBulkOperationStore $store;

    private DataScope $scope;

    private BulkOperations $api;

    private int $manifestReads = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema();
        $connection = DB::connection('api_tenant');
        $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_bulk_operations_table.php';
        $migration->up();
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
        $this->store = new SqlBulkOperationStore($connection, new ArrayDataScopeCodec, new GuardedTenantContextRestorer($contexts), $this->clock);
        $this->scope = new DataScope('account-1', null, $connection->getDriverName().':'.$connection->getDatabaseName(), 'actor-1', hash('sha256', 'epoch'));
        $this->api = new BulkOperations(
            $this->store,
            new TransferBulkExecutor(new BulkTransferFactory, $this->store, $this->clock, scopes: new ArrayDataScopeCodec),
            new BulkResultReader(
                static fn (): bool => true,
                function (): array {
                    $this->manifestReads++;

                    return ['media_type' => 'application/json', 'rows' => 1];
                },
            ),
            new BulkFailureReader(static fn (): array => ['records' => [], 'next' => null]),
            $this->clock,
        );
    }

    public function test_submit_is_accepted_once_and_poll_cancel_cover_the_status_matrix(): void
    {
        $first = $this->api->submit($this->scope, 'client-1', 'submission-1', $this->definition('bulk-1', 'submission-1'));
        $replay = $this->api->submit($this->scope, 'client-1', 'submission-1', $this->definition('bulk-other', 'submission-1'));
        $missingKey = $this->api->submit($this->scope, 'client-1', null, $this->definition('bulk-2', 'submission-2'));

        self::assertSame(202, $first->status());
        self::assertSame(202, $replay->status());
        self::assertSame('/api/v1/bulk-operations/bulk-1', $first->headers->get('Location'));
        self::assertSame('bulk-1', $first->getData(true)['operation_id']);
        self::assertSame('bulk-1', $replay->getData(true)['operation_id']);
        self::assertSame(400, $missingKey->status());

        $poll = $this->api->show($this->scope, 'client-1', 'bulk-1');
        self::assertSame(200, $poll->status());
        self::assertSame('2', $poll->headers->get('Retry-After'));
        self::assertFalse($poll->getData(true)['has_failures']);

        $cancel = $this->api->cancel($this->scope, 'client-1', 'bulk-1');
        self::assertSame(200, $cancel->status());
        self::assertSame('cancelled', $cancel->getData(true)['status']);
        self::assertSame(1, DB::connection('api_tenant')->table('sys_api_bulk_operations')->count());
    }

    public function test_result_matrix_is_not_ready_then_sealed_then_expired_and_partial_failures_are_visible(): void
    {
        $this->api->submit($this->scope, 'client-1', 'submission-run', $this->definition('bulk-run', 'submission-run'));
        self::assertSame(409, $this->api->result($this->scope, 'client-1', 'bulk-run')->status());

        $claimed = $this->store->claim($this->scope, 'worker-1');
        self::assertNotNull($claimed);
        $completed = $this->replace($claimed, BulkOperationStatus::Completed, ['invalid' => 2], ['id' => 'result-1']);
        self::assertTrue($this->store->save($completed, 'worker-1', $claimed->revision));

        $ready = $this->api->result($this->scope, 'client-1', 'bulk-run');
        $poll = $this->api->show($this->scope, 'client-1', 'bulk-run');
        self::assertSame(200, $ready->status());
        self::assertSame('application/json', $ready->getData(true)['media_type']);
        self::assertTrue($poll->getData(true)['has_failures']);
        self::assertSame(1, $this->manifestReads);

        $this->clock->advance(new DateInterval('P2D'));
        self::assertSame(410, $this->api->result($this->scope, 'client-1', 'bulk-run')->status());
        self::assertSame(1, $this->manifestReads);
    }

    private function definition(string $id, string $submission): BulkOperationDefinition
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
            extensionStamp: JsonDocuments::capture(['point' => 'api.operation', 'id' => 'notes.bulk-import', 'version' => '1']),
            inputRef: JsonDocuments::capture(['kind' => 'parameters', 'parameters' => ['limit' => 10]]),
        );
    }

    /**
     * @param  array<string, mixed>  $progress
     * @param  array<string, mixed>|null  $resultRef
     */
    private function replace(BulkOperation $operation, BulkOperationStatus $status, array $progress, ?array $resultRef): BulkOperation
    {
        return new BulkOperation(
            definition: $operation->definition,
            transferRef: JsonDocuments::nullable(JsonDocuments::optionalMap($operation->transferRef)),
            status: $status,
            terminalStatus: null,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: $operation->cancelRequestedAt,
            checkpoint: JsonDocuments::capture(['sequence' => '1']),
            progress: JsonDocuments::capture($progress),
            resultRef: JsonDocuments::nullable($resultRef),
            error: JsonDocuments::nullable(null),
            attempt: $operation->attempt,
            nextAttemptAt: $operation->nextAttemptAt,
            createdAt: $operation->createdAt,
            finishedAt: null,
            expiresAt: null,
        );
    }

    private function configure(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }
}
