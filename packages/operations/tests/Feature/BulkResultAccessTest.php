<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkOwnerRef;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Internal\BulkFailureReader;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use CoreX\Operations\Bulk\Internal\BulkResultReader;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\Fixtures\BulkExecutorFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;

final class BulkResultAccessTest extends TestCase
{
    private SqlBulkOperationStore $store;

    private DataScope $scope;

    private BulkExecutorFixture $owner;

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
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $connection = DB::connection('api_tenant');
        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
        $this->store = new SqlBulkOperationStore($connection, new ArrayDataScopeCodec, new CallerWorkScopeFixture($contexts), $clock);
        $this->scope = CallerWorkScopeFixture::scope('account-1', null, 'actor-1', hash('sha256', 'epoch'));
        $this->clock = $clock;
        $this->owner = new BulkExecutorFixture;
    }

    private FrozenClock $clock;

    public function test_foreign_caller_and_revoked_field_access_do_not_read_the_result(): void
    {
        $api = $this->api(false);
        $this->store->submit($this->definition());
        $claimed = $this->store->claim($this->scope, 'worker-1');
        self::assertNotNull($claimed);
        self::assertTrue($this->store->save($this->completed($claimed), 'worker-1', $claimed->revision));

        $foreign = CallerWorkScopeFixture::scope('account-9', null, 'actor-9', hash('sha256', 'other'));
        self::assertSame(404, $api->result($foreign, 'client-9', 'bulk-1')->status());
        self::assertSame(404, $api->show($this->scope, 'client-9', 'bulk-1')->status());
        self::assertSame(404, $api->result($this->scope, 'client-1', 'bulk-1')->status());
        self::assertSame(0, $this->owner->resultReads);
        self::assertSame(0, $this->owner->failureReads);
    }

    public function test_failure_cursor_rejects_limits_above_100(): void
    {
        $api = $this->api(true);
        $this->store->submit($this->definition());
        $claimed = $this->store->claim($this->scope, 'worker-1');
        self::assertNotNull($claimed);
        self::assertTrue($this->store->save($this->completed($claimed), 'worker-1', $claimed->revision));
        $page = $api->failurePage($this->scope, 'client-1', 'bulk-1', '10', 100);
        $denied = $api->failurePage($this->scope, 'client-1', 'bulk-1', null, 101);

        self::assertSame(200, $page->status());
        self::assertSame(422, $denied->status());
        self::assertSame(1, $this->owner->failureReads);
        self::assertSame('limit_exceeded', $denied->getData(true)['code']);
    }

    public function test_empty_failure_page_checks_current_owner_authority(): void
    {
        $this->store->submit($this->definition());
        $claimed = $this->store->claim($this->scope, 'worker-1');
        self::assertNotNull($claimed);
        self::assertTrue($this->store->save($this->completed($claimed, hasFailures: false), 'worker-1', $claimed->revision));

        $allowed = $this->api(true)->failurePage($this->scope, 'client-1', 'bulk-1', null, 50);
        self::assertSame(200, $allowed->status());
        self::assertSame([], $allowed->getData(true)['records']);
        self::assertSame(404, $this->api(false)->failurePage($this->scope, 'client-1', 'bulk-1', null, 50)->status());
        self::assertSame(1, $this->owner->resultReads);
        self::assertSame(0, $this->owner->failureReads);
    }

    private function api(bool $allowed): BulkOperations
    {
        $this->owner->allowRead = $allowed;

        return new BulkOperations(
            $this->store,
            new BulkResultReader(fn (): BulkExecutorFixture => $this->owner),
            new BulkFailureReader(fn (): BulkExecutorFixture => $this->owner),
            $this->clock,
        );
    }

    private function definition(): BulkOperationDefinition
    {
        return new BulkOperationDefinition(
            id: 'bulk-1',
            submissionId: 'submission-1',
            operationId: 'notes.bulk-import',
            major: 'v2',
            contractHash: hash('sha256', 'bulk-contract'),
            encodedScope: (new ArrayDataScopeCodec)->encode($this->scope),
            actorId: 'actor-1',
            clientId: 'client-1',
            extensionStamp: JsonDocuments::capture(['point' => 'api.operation', 'id' => 'notes.bulk-import', 'version' => '1']),
            inputRef: JsonDocuments::capture(['kind' => 'parameters', 'parameters' => ['limit' => 10]]),
        );
    }

    private function completed(BulkOperation $operation, bool $hasFailures = true): BulkOperation
    {
        return new BulkOperation(
            definition: $operation->definition,
            status: BulkOperationStatus::Completed,
            terminalStatus: null,
            revision: $operation->revision,
            leaseOwner: $operation->leaseOwner,
            leaseUntil: $operation->leaseUntil,
            cancelRequestedAt: null,
            checkpoint: null,
            progress: JsonDocuments::capture(['phase' => 'completed']),
            resultRef: null,
            error: JsonDocuments::nullable(null),
            attempt: $operation->attempt,
            nextAttemptAt: $operation->nextAttemptAt,
            createdAt: $operation->createdAt,
            finishedAt: null,
            expiresAt: null,
            ownerResultRef: new BulkOwnerRef('fixture/owner', 'result', 'result-1'),
            failurePageRef: $hasFailures ? new BulkOwnerRef('fixture/owner', 'failures', 'result-1') : null,
        );
    }
}
