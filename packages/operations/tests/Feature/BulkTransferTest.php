<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Internal\BulkWorker;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\Fixtures\BulkExecutorFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;
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

        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
        $this->store = new SqlBulkOperationStore(
            connection: $this->connection,
            scopeCodec: new ArrayDataScopeCodec,
            contexts: new CallerWorkScopeFixture($contexts),
            clock: $this->clock,
        );
        $this->scope = CallerWorkScopeFixture::scope('account-1', null, 'actor-1', hash('sha256', 'bulk-restore-epoch'));
    }

    public function test_worker_reclaims_an_unacknowledged_step_under_the_same_token(): void
    {
        $executor = new BulkExecutorFixture;
        $executor->loseReply = true;
        $worker = new BulkWorker($this->store, static fn (): BulkExecutorFixture => $executor, $this->clock, 'worker-1');
        $operation = $this->store->submit($this->definition());

        try {
            $worker->workOnce($this->scope);
            self::fail('The worker pretended to receive an owner acknowledgment.');
        } catch (RuntimeException $error) {
            self::assertSame('owner commit with lost acknowledgment', $error->getMessage());
        }

        self::assertSame(1, $executor->effects);
        self::assertNull($this->store->find($this->scope, $operation->definition->id)?->checkpoint);
        self::assertNull($worker->workOnce($this->scope));

        $this->clock->advance(new DateInterval('PT31S'));
        $replayed = $worker->workOnce($this->scope);
        self::assertSame(1, $replayed?->checkpoint?->sequence);
        self::assertSame($executor->attempts[0]->token, $executor->attempts[1]->token);
        self::assertSame(2, $executor->attempts[1]->rowLimit);
        self::assertSame(10, $executor->attempts[1]->timeLimitSeconds);
        self::assertSame(1, $executor->effects);
        self::assertNull($worker->workOnce($this->scope));

        $this->clock->advance(new DateInterval('PT5S'));
        $completed = $worker->workOnce($this->scope);
        self::assertSame(BulkOperationStatus::Completed, $completed?->status);
        self::assertSame(2, $completed?->checkpoint?->sequence);
        self::assertNotSame($executor->attempts[1]->token, $executor->attempts[2]->token);
        self::assertSame('fixture/owner', $completed->ownerResultRef?->owner);
    }

    private function definition(): BulkOperationDefinition
    {
        return new BulkOperationDefinition(
            'bulk-1', 'submission-1', 'notes.bulk-import', 'v2', hash('sha256', 'bulk-contract'),
            (new ArrayDataScopeCodec)->encode($this->scope), 'actor-1', 'client-1',
            JsonDocuments::capture(['point' => 'api.operation', 'owners' => ['fixture/owner']]),
            JsonDocuments::capture(['kind' => 'import']), 2, 10,
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
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
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
