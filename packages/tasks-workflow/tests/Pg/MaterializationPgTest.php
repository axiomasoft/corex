<?php

declare(strict_types=1);

use CoreX\Enums\ActorType;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;
use CoreX\Tasks\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Data\ActorData;
use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Data\TaskDraft;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\Stage;
use CoreX\Tasks\Models\Task;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;
use CoreX\TasksWorkflow\Exceptions\UnsupportedMaterialization;
use CoreX\TasksWorkflow\Internal\MaterializationHasher;
use CoreX\TasksWorkflow\Internal\MaterializationWakeupHandler;
use CoreX\TasksWorkflow\Tests\PgTestCase;
use CoreX\TasksWorkflow\Tests\Support\SeriesSeeder;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Contracts\WakeupSweeper;
use CoreX\Wakeup\Data\WakeupRequest;
use CoreX\Wakeup\WakeupServiceProvider;
use CoreX\Workflow\WorkflowServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(PgTestCase::class)->group('pg');

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

    $this->restoreEpoch = str_repeat('a', 64);
    $this->workspaceId = (string) Str::uuid7();
    $this->userId = (string) Str::uuid7();
    DB::table('wsp_workspaces')->insert(['id' => $this->workspaceId, 'name' => 'Main']);
    DB::table('users')->insert(['id' => $this->userId, 'name' => 'Owner']);
    $this->actor = new ActorData(type: ActorType::User, id: $this->userId);
    $this->project = Project::query()->create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Launch',
        'created_by' => $this->actor->toArray(),
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
    return new DataScope(
        context: app(TenantContextResolver::class)->current(),
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
    $materializer = app(TaskRecurrenceCommand::class);

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
    $materializer = app(TaskRecurrenceCommand::class);
    $first = $materializer->materialize(pgScope($this), $series, $window, $token);
    $replay = $materializer->materialize(pgScope($this), $series, $window, $token);

    expect($replay->pageDigest)->toBe($first->pageDigest)
        ->and(DB::table('corex_task_operations')->where('request_key', 'pg-02-op')->count())->toBe(1);
});

it('MAT-06 schedules durable wakeup and materializes through the local handler', function (): void {
    app()->register(WakeupServiceProvider::class);
    expect($this->migrateWakeup())->toBe(0);
    app()['config']->set('corex-wakeup.handlers', [
        'task-recurrence.materialize' => MaterializationWakeupHandler::class,
    ]);
    app()['config']->set('corex-task-recurrence.wakeup.enabled', true);
    app()->forgetScopedInstances();

    $set = SeriesSeeder::dailySet('series-pg-06', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-06', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    app(TaskRecurrenceCommand::class)->materialize(pgScope($this), $series, $window, pgToken($this, $series, $window, 'pg-06-a'));

    expect(DB::table('sys_wakeup_requests')->where('request_key', 'materialize:series-pg-06')->exists())->toBeTrue();

    $context = app(TenantContextResolver::class)->current();
    app(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: 'task-recurrence.materialize',
        requestKey: 'manual:series-pg-06',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: [
            'seriesId' => 'series-pg-06',
            'immutableRevision' => 'rev-1',
            'definitionHash' => $set->definitionHash,
            'start' => '2026-09-01',
            'end' => '2026-09-05',
        ],
    ));

    app(WakeupSweeper::class)->sweep($context, 10);
    expect(Task::query()->count())->toBeGreaterThanOrEqual(1);
});

it('MAT-07 captures workflow completion inside the task transaction when enabled', function (): void {
    app()->register(WorkflowServiceProvider::class);
    expect($this->migrateWorkflow())->toBe(0);
    app()['config']->set('corex-workflow.enforce_declarations', false);
    app()['config']->set('corex-task-recurrence.completion_capture.enabled', true);
    app()->forgetScopedInstances();

    $set = SeriesSeeder::dailySet('series-pg-07', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-07', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $receipt = app(TaskRecurrenceCommand::class)->materialize(pgScope($this), $series, $window, pgToken($this, $series, $window, 'pg-07-a'));
    $taskId = $receipt->taskRefs[0]->id;

    app(CompleteTaskCommand::class)->handle(new CompleteTaskData(taskId: $taskId, actor: $this->actor));

    expect(DB::table('sys_workflow_intents')->where('subject_ref', 'task:'.$taskId)->exists())->toBeTrue()
        ->and(DB::table('corex_task_operations')->where('kind', 'completionCapture')->exists())->toBeTrue();
});

it('MAT-08 denies cross-account restore epoch reuse on postgres', function (): void {
    $set = SeriesSeeder::dailySet('series-pg-08', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-pg-08', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $scope = new DataScope(
        context: app(TenantContextResolver::class)->current(),
        connectionIdentity: 'pgsql:another_account',
        actorId: $this->userId,
        restoreEpoch: $this->restoreEpoch,
    );

    expect(fn () => app(TaskRecurrenceCommand::class)->materialize(
        $scope,
        $series,
        $window,
        new WriteToken('pg-08', MaterializationHasher::payload($scope, $series, $window), $series->immutableRevision),
    ))->toThrow(UnsupportedMaterialization::class);
});
