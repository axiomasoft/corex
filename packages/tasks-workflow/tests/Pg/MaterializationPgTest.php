<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\CompiledRegistry;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Internal\Models\Stage;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;
use CoreX\TasksWorkflow\Exceptions\UnsupportedMaterialization;
use CoreX\TasksWorkflow\Internal\MaterializationHasher;
use CoreX\TasksWorkflow\Internal\TasksWorkflowOptions;
use CoreX\TasksWorkflow\Tests\PgTestCase;
use CoreX\TasksWorkflow\Tests\Support\SeriesSeeder;

require_once __DIR__.'/../../../wakeup/tests/Support/PackageBridge.php';
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use CoreX\Workflow\Internal\WorkflowOptions;
use CoreX\Workflow\WorkflowServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(PgTestCase::class)->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    expect($this->migrateFixtures())->toBe(0)
        ->and($this->migrateTasks())->toBe(0)
        ->and($this->migrateTasksWorkflow())->toBe(0);

    $modules = Mockery::mock(ModuleRegistry::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isActive')->byDefault()->andReturnTrue();
        $mock->shouldReceive('compiled')->byDefault()->andReturn(CompiledRegistry::empty());
    });
    app()->instance(ModuleRegistry::class, $modules);

    $access = Mockery::mock(TaskAccess::class);
    $access->shouldReceive('authorizeCreate')->byDefault()->andReturnNull();
    $access->shouldReceive('authorizeMove')->byDefault()->andReturnNull();
    $access->shouldReceive('authorizeTarget')->byDefault()->andReturnNull();
    app()->instance(TaskAccess::class, $access);

    $this->restoreEpoch = str_repeat('a', 64);
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
});

function pgScope(object $test): DataScope
{
    return DataScope::fromTenantContext(
        context: resolve(TenantContextResolver::class)->current(),
        connectionIdentity: 'pgsql:'.DB::connection('pgsql')->getDatabaseName(),
        actorId: $test->userId,
        restoreEpoch: $test->restoreEpoch,
    );
}

function pgToken(object $test, SeriesReference $series, MaterializationWindow $window, string $operationKey): WriteToken
{
    $scope = pgScope($test);

    return new WriteToken(
        operationKey: $operationKey,
        payloadHash: MaterializationHasher::payload($scope, $series, $window),
        expectedRecordVersion: $series->immutableRevision,
    );
}

it('MAT-01 serializes concurrent materialization to one task per occurrence', function (): void {
    $set = SeriesSeeder::dailySet('series-pg-01', 'rev-1', 2);
    $series = SeriesSeeder::seed('series-pg-01', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $materializer = resolve(TaskRecurrenceCommand::class);

    $materializer->materialize(pgScope($this), $series, $window, pgToken($this, $series, $window, 'pg-01-a'));
    $second = $materializer->materialize(pgScope($this), $series, $window, pgToken($this, $series, $window, 'pg-01-b'));

    expect($second->noop)->toBe(2)
        ->and(Task::query()->count())->toBe(2);
});

it('MAT-02 replays durable receipts after a failed outer attempt', function (): void {
    $set = SeriesSeeder::dailySet('series-pg-02', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-02', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $token = pgToken($this, $series, $window, 'pg-02-op');
    $materializer = resolve(TaskRecurrenceCommand::class);
    $first = $materializer->materialize(pgScope($this), $series, $window, $token);
    $replay = $materializer->materialize(pgScope($this), $series, $window, $token);

    expect($replay->pageDigest)->toBe($first->pageDigest)
        ->and(DB::table('corex_task_operations')->where('request_key', 'pg-02-op')->count())->toBe(1);
});

it('MAT-06 schedules a stamped complete payload and materializes through the handler', function (): void {
    registerMaterializationWakeup();
    expect($this->migrateWakeup())->toBe(0);
    app()['config']->set('corex-tasks-workflow.wakeup.enabled', true);
    app()->forgetInstance(TasksWorkflowOptions::class);
    app()->forgetScopedInstances();

    $set = SeriesSeeder::dailySet('series-pg-06', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-06', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    resolve(TaskRecurrenceCommand::class)->materialize(pgScope($this), $series, $window, pgToken($this, $series, $window, 'pg-06-a'));

    $stored = json_decode(
        (string) DB::table('sys_wakeup_requests')->where('kind', MaterializationHasher::HANDLER_KIND)->value('payload'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($stored['_corex']['extension']['id'])->toBe(MaterializationHasher::HANDLER_KIND)
        ->and($stored['_corex']['extension']['version'])->toBe(MaterializationHasher::HANDLER_VERSION)
        ->and($stored['data'])->toMatchArray(MaterializationHasher::wakeupPayload(series: $series, window: $window));

    $context = resolve(TenantContextResolver::class)->current();
    $second = SeriesSeeder::dailySet('series-pg-06b', 'rev-1', 1);
    $secondSeries = SeriesSeeder::seed('series-pg-06b', $second, $this->template, $this->restoreEpoch);
    expect(resolve(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: MaterializationHasher::HANDLER_KIND,
        requestKey: 'manual:series-pg-06b',
        fireAt: new DateTimeImmutable('2026-09-16T10:00:00Z'),
        window: null,
        payload: JsonDocuments::capture(MaterializationHasher::wakeupPayload(
            series: $secondSeries,
            window: new MaterializationWindow(start: '2026-09-01', end: '2026-09-05'),
        )),
    ))->status)->toBe('created');

    $result = wakeup_bridge_sweep($context, 10);

    expect($result->completed)->toBeGreaterThanOrEqual(1)
        ->and(Task::query()->where('title', 'Recurring maintenance')->count())->toBeGreaterThanOrEqual(2)
        ->and(DB::table('sys_wakeup_requests')->where('request_key', 'manual:series-pg-06b')->value('status'))->toBe('completed');
});

it('MAT-06b quarantines an exact handler version mismatch without invoking materialization', function (): void {
    registerMaterializationWakeup();
    expect($this->migrateWakeup())->toBe(0);
    $set = SeriesSeeder::dailySet('series-pg-06b-mismatch', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-06b-mismatch', $set, $this->template, $this->restoreEpoch);
    $registry = resolve(wakeup_bridge_registry());
    $definition = $registry->definition(MaterializationHasher::HANDLER_KIND);
    $envelope = $registry->envelope(
        definition: $definition,
        data: MaterializationHasher::wakeupPayload(
            series: $series,
            window: new MaterializationWindow(start: '2026-09-01', end: '2026-09-05'),
        ),
    );
    $envelope['_corex']['extension']['version'] = 'task-materialization/0';
    $now = new DateTimeImmutable('2026-09-16T10:00:00Z');
    DB::table('sys_wakeup_requests')->insert([
        'id' => (string) Str::uuid7(),
        'workspace_scope_key' => 'account',
        'kind' => MaterializationHasher::HANDLER_KIND,
        'request_key' => 'mismatch:series-pg-06b',
        'request_digest' => hash('sha256', 'mismatch'),
        'fire_at' => $now,
        'payload' => json_encode($envelope, JSON_THROW_ON_ERROR),
        'status' => 'pending',
        'generation' => 0,
        'attempt' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $result = wakeup_bridge_sweep(resolve(TenantContextResolver::class)->current(), 10);

    expect($result->failed)->toBe(1)
        ->and(Task::query()->count())->toBe(0)
        ->and(DB::table('sys_wakeup_requests')->where('request_key', 'mismatch:series-pg-06b')->value('failure_code'))->toBe('handler-version-mismatch');
});

it('MAT-07 captures workflow completion inside the task transaction when enabled', function (): void {
    app()->register(WorkflowServiceProvider::class);
    expect($this->migrateWorkflow())->toBe(0);
    app()['config']->set('corex-workflow.enforce_declarations', false);
    app()->forgetInstance(WorkflowOptions::class);
    app()['config']->set('corex-tasks-workflow.completion_capture.enabled', true);
    app()->forgetInstance(TasksWorkflowOptions::class);
    app()->forgetScopedInstances();

    $set = SeriesSeeder::dailySet('series-pg-07', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-07', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $receipt = resolve(TaskRecurrenceCommand::class)->materialize(pgScope($this), $series, $window, pgToken($this, $series, $window, 'pg-07-a'));
    $taskId = $receipt->taskRefs[0]->id;

    resolve(CompleteTaskCommand::class)->handle(new CompleteTaskData(taskId: $taskId, actor: $this->actor));

    expect(DB::table('sys_workflow_intents')->where('subject_ref', 'task:'.$taskId)->exists())->toBeTrue()
        ->and(DB::table('sys_workflow_intents')->where('subject_ref', 'task:'.$taskId)->value('version_ref'))->toBe('task-completion/1')
        ->and(DB::table('sys_workflow_intents')->where('subject_ref', 'task:'.$taskId)->value('definition_ref'))->toBe('task-completion/1')
        ->and(DB::table('corex_task_operations')->where('kind', 'completionCapture')->exists())->toBeTrue();
});

it('MAT-08 denies cross-account restore epoch reuse on postgres', function (): void {
    $set = SeriesSeeder::dailySet('series-pg-08', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-08', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $scope = DataScope::fromTenantContext(
        context: resolve(TenantContextResolver::class)->current(),
        connectionIdentity: 'pgsql:another_account',
        actorId: $this->userId,
        restoreEpoch: $this->restoreEpoch,
    );

    expect(fn () => resolve(TaskRecurrenceCommand::class)->materialize(
        $scope,
        $series,
        $window,
        new WriteToken('pg-08', MaterializationHasher::payload($scope, $series, $window), $series->immutableRevision),
    ))->toThrow(UnsupportedMaterialization::class);
});

function registerMaterializationWakeup(): void
{
    app()['config']->set('corex-tasks-workflow.wakeup.enabled', true);
    app()->forgetInstance(TasksWorkflowOptions::class);
    app()->register(TasksWorkflowServiceProvider::class, force: true);
    wakeup_bridge_register();
    app()->forgetInstance(wakeup_bridge_registry());
    app()->forgetScopedInstances();
}
