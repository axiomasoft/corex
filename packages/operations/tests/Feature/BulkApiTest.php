<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkOwnerRef;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Internal\BulkFailureReader;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use CoreX\Operations\Bulk\Internal\BulkResultReader;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Bulk\Laravel\BulkRouteRegistrar;
use CoreX\Operations\Bulk\Laravel\SubmitBulkOperation;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Tests\Fixtures\BulkExecutorFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;

final class BulkApiTest extends TestCase
{
    private FrozenClock $clock;

    private SqlBulkOperationStore $store;

    private DataScope $scope;

    private BulkOperations $api;

    private BulkExecutorFixture $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema();
        $connection = DB::connection('api_tenant');

        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
        $this->store = new SqlBulkOperationStore($connection, new ArrayDataScopeCodec, new CallerWorkScopeFixture($contexts), $this->clock);
        $this->scope = CallerWorkScopeFixture::scope('account-1', null, 'actor-1', hash('sha256', 'epoch'));
        $this->owner = new BulkExecutorFixture;
        $this->api = new BulkOperations(
            $this->store,
            new BulkResultReader(fn (): BulkExecutorFixture => $this->owner),
            new BulkFailureReader(fn (): BulkExecutorFixture => $this->owner),
            $this->clock,
        );
    }

    public function test_submit_is_accepted_once_and_poll_cancel_cover_the_status_matrix(): void
    {
        BulkRouteRegistrar::register($this->app);
        $firstRow = $this->store->submit($this->definition('bulk-1', 'submission-1'));
        $location = '/api/v1/bulk-operations/bulk-1';
        $first = $this->api->accepted($this->scope, 'client-1', $firstRow->definition->id, $location);
        $replayedRow = $this->store->submit($this->definition('bulk-other', 'submission-1'));
        $replay = $this->api->accepted($this->scope, 'client-1', $replayedRow->definition->id, $location);

        self::assertSame(202, $first->status());
        self::assertSame(202, $replay->status());
        self::assertSame('/api/v1/bulk-operations/bulk-1', $first->headers->get('Location'));
        self::assertSame('bulk-1', $first->getData(true)['operation_id']);
        self::assertSame('bulk-1', $replay->getData(true)['operation_id']);

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
        $this->store->submit($this->definition('bulk-run', 'submission-run'));
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
        self::assertSame(1, $this->owner->resultReads);

        $this->clock->advance(new DateInterval('P2D'));
        self::assertSame(410, $this->api->result($this->scope, 'client-1', 'bulk-run')->status());
        self::assertSame(1, $this->owner->resultReads);
    }

    public function test_public_submit_denies_missing_host_then_replays_the_declared_operation_once(): void
    {
        $db = DB::connection('api_tenant');
        $this->app->instance(Connection::class, $db);
        $this->app['config']->set('database.default', 'api_tenant');
        $this->app['config']->set('app.key', 'source-bulk-identity-key');
        $tenants = new ContextFixture;
        $tenants->initialize(new AccountRef('account-1', 'a', 'active', [], []));
        $this->app->instance(TenantContextResolver::class, $tenants);
        $this->app->instance(TenancyManager::class, $tenants);
        $host = new IdentityFixture;
        $this->app->instance(PrincipalResolver::class, $host);
        $scope = CallerWorkScopeFixture::scope('account-1', null, $host->principalId(), hash('sha256', 'route'));
        CallerWorkScopeFixture::bind($this->app, $scope);
        $clients = Mockery::mock(ClientScopeAuthorizer::class);
        $clients->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(ClientScopeAuthorizer::class, $clients);
        $authorizer = Mockery::mock(OperationAuthorizer::class);
        $authorizer->shouldReceive('allows')->andReturnTrue();
        $this->app->instance(OperationAuthorizer::class, $authorizer);
        $references = Mockery::mock(ReferenceAdmission::class);
        $references->shouldReceive('enlist')->withArgs(static fn ($scope, Connection $connection): bool => $connection === $db && $connection->transactionLevel() > 0);
        $this->app->instance(ReferenceAdmission::class, $references);

        $schema = ['type' => 'object'];
        $api = Api::operation('notes.bulk-import', 'POST', '/notes/bulk-submit', SubmitBulkOperation::class, 'notes.import', $schema, $schema, successStatus: 202)
            ->asBulkCommand()->idempotency('required')->effect('tenant_transactional')->cost(['base' => 2]);
        $api->bulkExecutionEnabled = true;
        $modules = new ModuleFixture(resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$api]),
        ], installedPackages: []));
        $this->app->instance(ModuleRegistry::class, $modules);
        $operation = resolve(OperationRegistry::class)->all('v1')[0];
        BulkRouteRegistrar::register($this->app);

        $send = function (string $id, int $rows, ?string $key) use ($scope, $operation) {
            $request = Request::create('/api/v1/bulk-operations', 'POST', server: ['HTTP_IDEMPOTENCY_KEY' => $key]);
            $request->attributes->set('corex.client_id', 'client-1');
            $request->attributes->set('corex.bulk_definition', new BulkOperationDefinition(
                $id, 'ignored', $operation->id, 'v1', $operation->contractHash,
                (new ArrayDataScopeCodec)->encode($scope), $scope->principalId, 'client-1',
                JsonDocuments::capture(['point' => 'api.operation']), JsonDocuments::capture(['kind' => 'import', 'rows' => $rows]),
            ));
            $this->app->instance('request', $request);

            return $this->app['router']->dispatch($request);
        };

        self::assertSame(400, $send('no-key', 1, null)->getStatusCode());
        self::assertSame(503, $send('no-host', 1, 'key')->getStatusCode());
        self::assertSame(0, $db->table('sys_api_idempotency')->count());
        self::assertSame(0, $db->table('sys_api_bulk_operations')->count());

        $this->app->instance(BulkExecutor::class, $this->owner);
        $this->app->instance(BulkOwner::class, $this->owner);
        $first = $send('original', 1, 'key');
        $replayed = $send('different-candidate', 1, 'key');
        self::assertSame(202, $first->getStatusCode());
        self::assertSame($first->getContent(), $replayed->getContent());
        self::assertSame('/api/v1/bulk-operations/original', $first->headers->get('Location'));
        self::assertSame(409, $send('changed', 2, 'key')->getStatusCode());
        self::assertSame(1, $db->table('sys_api_idempotency')->count());
        self::assertSame(1, $db->table('sys_api_bulk_operations')->count());
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
            status: $status,
            terminalStatus: null,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: $operation->cancelRequestedAt,
            checkpoint: null,
            progress: JsonDocuments::capture($progress),
            resultRef: null,
            error: JsonDocuments::nullable(null),
            attempt: $operation->attempt,
            nextAttemptAt: $operation->nextAttemptAt,
            createdAt: $operation->createdAt,
            finishedAt: null,
            expiresAt: null,
            ownerResultRef: $resultRef === null ? null : new BulkOwnerRef('fixture/owner', 'result', 'result-1'),
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
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }
}
