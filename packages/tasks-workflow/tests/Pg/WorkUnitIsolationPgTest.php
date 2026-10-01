<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Internal\AllowAllDataScopeAuthorizer;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Internal\Models\Stage;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\TasksWorkflow\Tests\PgTestCase;

uses(PgTestCase::class);
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Internal\MaterializationHasher;
use CoreX\TasksWorkflow\Tests\Support\SeriesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

final class PgWorkUnitContextResolver implements TenantContextResolver
{
    public function __construct(public TenantContext $context) {}

    public function current(): TenantContext
    {
        return $this->context;
    }
}

function pgWorkUnitAccount(string $id): AccountRef
{
    return new AccountRef(id: $id, slug: $id, status: 'active', features: [], limits: []);
}

function pgWorkUnitScope(TenantContext $context, string $connectionIdentity, string $actorId, string $restoreEpoch): DataScope
{
    return DataScope::fromTenantContext(
        context: $context,
        connectionIdentity: $connectionIdentity,
        actorId: $actorId,
        restoreEpoch: $restoreEpoch,
    );
}

beforeEach(function (): void {
    $this->resetPgSchema();
    expect($this->migrateFixtures())->toBe(0)
        ->and($this->migrateTasks())->toBe(0)
        ->and($this->migrateFile(__DIR__.'/../../tasks/database/migrations/2026_09_15_000071_create_task_operation_receipts.php'))->toBe(0)
        ->and($this->migrateTasksWorkflow())->toBe(0);

    $modules = Mockery::mock(ModuleRegistry::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isActive')->byDefault()->andReturnTrue();
    });
    app()->instance(ModuleRegistry::class, $modules);

    $access = Mockery::mock(TaskAccess::class);
    $access->shouldReceive('authorizeCreate')->byDefault()->andReturnNull();
    $access->shouldReceive('authorizeMove')->byDefault()->andReturnNull();
    $access->shouldReceive('authorizeTarget')->byDefault()->andReturnNull();
    app()->instance(TaskAccess::class, $access);

    $this->restoreEpochA = str_repeat('a', 64);
    $this->restoreEpochB = str_repeat('b', 64);
    $this->workspaceId = (string) Str::uuid7();
    $this->userId = (string) Str::uuid7();
    DB::table('wsp_workspaces')->insert(['id' => $this->workspaceId, 'name' => 'Main']);
    DB::table('users')->insert(['id' => $this->userId, 'name' => 'Owner']);
    $this->actor = new ActorData(type: ActorType::User, id: $this->userId);
    $this->project = Project::query()->create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Launch',
        'created_by' => JsonDocuments::decodeTree($this->actor->toArray()),
    ]);
    $this->stage = Stage::query()->create([
        'project_id' => $this->project->getKey(),
        'code' => 'todo',
        'name' => ['ru' => 'К работе'],
    ]);
    $this->template = new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Recurring maintenance',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->stage->getKey(),
    );
    $this->databaseName = DB::connection('pgsql')->getDatabaseName();
})->group('pg');

it('isolates sequential postgres materializations when the first unit throws', function (): void {
    $container = app();
    $contextA = new TenantContext(account: pgWorkUnitAccount('11111111-1111-7111-8111-111111111111'), boxed: true);
    $contextB = new TenantContext(account: pgWorkUnitAccount('22222222-2222-7222-8222-222222222222'), boxed: true);
    $connectionA = 'pgsql:'.$this->databaseName;
    $connectionB = 'pgsql:'.$this->databaseName;

    $setA = SeriesSeeder::dailySet('series-wui-a', 'rev-a', 1);
    $seriesA = SeriesSeeder::seed('series-wui-a', $setA, $this->template, $this->restoreEpochA);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-03');

    $container->instance(TenantContextResolver::class, new PgWorkUnitContextResolver($contextA));

    $originalAuthorizer = $container->make(DataScopeAuthorizer::class);
    $container->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);
    $container->forgetScopedInstances();

    $scopeA = pgWorkUnitScope($contextA, $connectionA, $this->userId, $this->restoreEpochA);
    $scopedFromA = $container->make(TenantContextRestorer::class);
    $tokenA = new WriteToken(
        operationKey: 'wui-a',
        payloadHash: MaterializationHasher::payload($scopeA, $seriesA, $window),
        expectedRecordVersion: $seriesA->immutableRevision,
    );

    try {
        $container->make(TaskRecurrenceCommand::class)->materialize($scopeA, $seriesA, $window, $tokenA);

        throw new RuntimeException('work-unit-pg-pass-a');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('work-unit-pg-pass-a');
    } finally {
        $container->instance(DataScopeAuthorizer::class, $originalAuthorizer);
        $container->forgetScopedInstances();
    }

    expect($container->make(DataScopeAuthorizer::class))->toBe($originalAuthorizer)
        ->and(Task::query()->count())->toBe(1);

    $container->forgetScopedInstances();

    $container->instance(TenantContextResolver::class, new PgWorkUnitContextResolver($contextB));

    expect(fn () => $container->make(TenantContextRestorer::class)->restore($scopeA))
        ->toThrow(ScopeContextMismatch::class);

    $scopedBeforeB = $container->make(TenantContextRestorer::class);
    expect($scopedBeforeB)->not->toBe($scopedFromA);

    $container->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);
    $container->forgetScopedInstances();

    $setB = SeriesSeeder::dailySet('series-wui-b', 'rev-b', 1);
    $seriesB = SeriesSeeder::seed('series-wui-b', $setB, $this->template, $this->restoreEpochB);
    $scopeB = pgWorkUnitScope($contextB, $connectionB, $this->userId, $this->restoreEpochB);
    $tokenB = new WriteToken(
        operationKey: 'wui-b',
        payloadHash: MaterializationHasher::payload($scopeB, $seriesB, $window),
        expectedRecordVersion: $seriesB->immutableRevision,
    );

    $receiptB = $container->make(TaskRecurrenceCommand::class)->materialize($scopeB, $seriesB, $window, $tokenB);

    expect($receiptB->noop)->toBe(0)
        ->and(Task::query()->count())->toBe(2);
})->group('pg');
