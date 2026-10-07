<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Planning\Contracts\TaskPlanningCommand;
use CoreX\Tasks\Planning\Data\PlanningOperation;
use CoreX\Tasks\Planning\Data\PlanningSubject;
use CoreX\Tasks\Planning\Data\PlanningToken;
use CoreX\Tasks\Planning\Exceptions\InvalidPlanningDraft;
use CoreX\Tasks\Planning\Exceptions\PlanningConflict;
use CoreX\Tasks\Planning\Internal\PlanningHasher;
use CoreX\Tasks\Project\Data\ProjectDraft;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Sprint\Data\SprintDraft;
use CoreX\Tasks\Sprint\Internal\Models\Sprint;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Time\Data\TimeLogDraft;
use CoreX\Tasks\Time\Enums\TimeLogSource;
use CoreX\Tasks\Time\Internal\Models\TimeLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->workspaceId = (string) Str::uuid7();
    $this->otherWorkspaceId = (string) Str::uuid7();
    $this->userId = (string) Str::uuid7();

    DB::table('wsp_workspaces')->insert([
        ['id' => $this->workspaceId, 'name' => 'Main'],
        ['id' => $this->otherWorkspaceId, 'name' => 'Other'],
    ]);
    DB::table('users')->insert(['id' => $this->userId, 'name' => 'Owner']);

    $this->actor = new ActorData(type: ActorType::User, id: $this->userId);
    $this->context = resolve(TenantContextResolver::class)->current();
});

function planningToken(string $requestKey, string $payloadHash): PlanningToken
{
    return new PlanningToken(requestKey: $requestKey, payloadHash: $payloadHash);
}

it('keeps existing create and complete commands byte-compatible', function (): void {
    $create = new ReflectionClass(CreateTaskCommand::class);
    $complete = new ReflectionClass(CompleteTaskCommand::class);

    expect($create->getMethod('handle')->getParameters()[0]->getType()?->getName())
        ->toBe(TaskDraft::class)
        ->and($complete->getMethod('handle')->getParameters()[0]->getType()?->getName())
        ->toBe(CompleteTaskData::class);

    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Legacy command',
        actor: $this->actor,
    ));

    resolve(CompleteTaskCommand::class)->handle(new CompleteTaskData(
        taskId: $reference->id,
        actor: $this->actor,
    ));

    expect(Task::query()->findOrFail($reference->id)->status)->toBe(TaskStatus::Done);
});

it('creates a project in the trusted workspace context', function (): void {
    $draft = new ProjectDraft(
        workspaceId: $this->workspaceId,
        name: '  Maintenance  ',
        actor: $this->actor,
    );
    $token = planningToken(
        requestKey: 'prim-02-project',
        payloadHash: PlanningHasher::project($draft, $this->context),
    );

    $reference = resolve(TaskPlanningCommand::class)->createProject($draft, $token);

    expect($reference->type)->toBe('tsk.project')
        ->and($reference->display)->toBe('Maintenance')
        ->and(Project::query()->count())->toBe(1);
});

it('denies a project in another narrowed workspace', function (): void {
    $current = resolve(TenantContextResolver::class)->current();
    $narrowed = new TenantContext(
        account: $current->account,
        workspace: new WorkspaceRef(
            id: $this->otherWorkspaceId,
            slug: 'other',
            type: 'branch',
            path: 'other',
            parentId: null,
        ),
    );
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturn($narrowed);
    app()->instance(TenantContextResolver::class, $resolver);

    $draft = new ProjectDraft(
        workspaceId: $this->workspaceId,
        name: 'Foreign',
        actor: $this->actor,
    );
    $token = planningToken(
        requestKey: 'prim-02-foreign',
        payloadHash: PlanningHasher::project($draft, $narrowed),
    );

    expect(fn () => resolve(TaskPlanningCommand::class)->createProject($draft, $token))
        ->toThrow(ValidationException::class);

    expect(Project::query()->count())->toBe(0);
});

it('replays the same project request key and rejects changed payloads', function (): void {
    $draft = new ProjectDraft(
        workspaceId: $this->workspaceId,
        name: 'Dedup project',
        actor: $this->actor,
    );
    $token = planningToken(
        requestKey: 'prim-03-project',
        payloadHash: PlanningHasher::project($draft, $this->context),
    );
    $command = resolve(TaskPlanningCommand::class);

    $first = $command->createProject($draft, $token);
    $second = $command->createProject($draft, $token);

    expect($second->id)->toBe($first->id)
        ->and(Project::query()->count())->toBe(1);

    $changed = new ProjectDraft(
        workspaceId: $this->workspaceId,
        name: 'Different name',
        actor: $this->actor,
    );
    $conflict = planningToken(
        requestKey: 'prim-03-project',
        payloadHash: PlanningHasher::project($changed, $this->context),
    );

    expect(fn () => $command->createProject($changed, $conflict))
        ->toThrow(PlanningConflict::class);
});

it('validates sprint parent and date ordering before writes', function (): void {
    $project = Project::query()->create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Parent',
        'created_by' => $this->actor->toArray(),
    ]);

    $draft = new SprintDraft(
        projectId: (string) $project->getKey(),
        name: 'Sprint A',
        startDate: '2026-09-20',
        endDate: '2026-09-10',
        actor: $this->actor,
    );
    $token = planningToken(
        requestKey: 'prim-03-sprint-invalid',
        payloadHash: PlanningHasher::sprint($draft, $this->context, '2026-09-20', '2026-09-10'),
    );

    expect(fn () => resolve(TaskPlanningCommand::class)->createSprint($draft, $token))
        ->toThrow(InvalidPlanningDraft::class);

    expect(Sprint::query()->count())->toBe(0);
});

it('records time atomically with spent totals and operation receipts', function (): void {
    $task = Task::query()->create([
        'workspace_id' => $this->workspaceId,
        'title' => 'Tracked',
        'created_by' => $this->actor->toArray(),
        'spent_minutes' => 15,
    ]);
    $draft = new TimeLogDraft(
        taskId: (string) $task->getKey(),
        userId: $this->userId,
        minutes: 30,
        spentOn: '2026-09-17',
        comment: 'Work',
        source: TimeLogSource::Manual,
        actor: $this->actor,
    );
    $token = planningToken(
        requestKey: 'prim-04-time',
        payloadHash: PlanningHasher::timeLog($draft, $this->context),
    );

    DB::beginTransaction();

    $reference = resolve(TaskPlanningCommand::class)->recordTime($draft, $token);

    expect(TimeLog::query()->count())->toBe(1)
        ->and(Task::query()->findOrFail($task->getKey())->spent_minutes)->toBe(45)
        ->and(DB::table('corex_task_operations')->count())->toBe(1);

    DB::rollBack();

    expect(TimeLog::query()->count())->toBe(0)
        ->and(Task::query()->findOrFail($task->getKey())->spent_minutes)->toBe(15)
        ->and(DB::table('corex_task_operations')->count())->toBe(0)
        ->and($reference->type)->toBe('tsk.time-log');
});

it('preserves existing completion policy without new workflow ownership', function (): void {
    $task = Task::query()->create([
        'workspace_id' => $this->workspaceId,
        'title' => 'Completion policy',
        'created_by' => $this->actor->toArray(),
        'status' => TaskStatus::Cancelled,
    ]);

    expect(fn () => resolve(CompleteTaskCommand::class)->handle(new CompleteTaskData(
        taskId: (string) $task->getKey(),
        actor: $this->actor,
    )))->toThrow(ValidationException::class);
});

it('supports boxed null workspace context for planning subjects', function (): void {
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturn(new TenantContext(
        account: resolve(TenantContextResolver::class)->current()->account,
        workspace: null,
    ));
    app()->instance(TenantContextResolver::class, $resolver);

    $context = resolve(TenantContextResolver::class)->current();
    $draft = new ProjectDraft(
        workspaceId: $this->workspaceId,
        name: 'Boxed',
        actor: $this->actor,
    );
    $token = planningToken(
        requestKey: 'prim-07-boxed',
        payloadHash: PlanningHasher::project($draft, $context),
    );

    $reference = resolve(TaskPlanningCommand::class)->createProject($draft, $token);

    expect($reference->display)->toBe('Boxed');
});

it('ships deny-by-default planning authorization', function (): void {
    app()->forgetInstance(TaskPlanningAccess::class);

    $draft = new ProjectDraft(
        workspaceId: $this->workspaceId,
        name: 'Denied',
        actor: $this->actor,
    );

    expect(fn () => resolve(TaskPlanningAccess::class)->authorize(
        operation: PlanningOperation::CreateProject,
        subject: new PlanningSubject(
            workspaceId: $this->workspaceId,
            projectId: null,
            taskId: null,
            userId: $this->userId,
        ),
        actor: $this->actor,
        context: resolve(TenantContextResolver::class)->current(),
    ))->toThrow(AuthorizationException::class);
});

it('keeps canonical task tables and adds only the neutral operation receipt table', function (): void {
    $expected = [
        'corex_task_operations',
        'tsk_projects',
        'tsk_sprints',
        'tsk_stages',
        'tsk_task_targets',
        'tsk_task_watchers',
        'tsk_tasks',
        'tsk_time_logs',
        'tsk_timers',
    ];

    foreach ($expected as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('creates maintenance project sprint and time log through the neutral fixture', function (): void {
    $projectDraft = new ProjectDraft(
        workspaceId: $this->workspaceId,
        name: 'Maintenance',
        actor: $this->actor,
    );
    $projectToken = planningToken(
        requestKey: 'prim-08-project',
        payloadHash: PlanningHasher::project($projectDraft, $this->context),
    );
    $project = resolve(TaskPlanningCommand::class)->createProject($projectDraft, $projectToken);

    $sprintDraft = new SprintDraft(
        projectId: $project->id,
        name: 'Cycle 1',
        startDate: '2026-09-01',
        endDate: '2026-09-14',
        actor: $this->actor,
    );
    $sprintToken = planningToken(
        requestKey: 'prim-08-sprint',
        payloadHash: PlanningHasher::sprint($sprintDraft, $this->context, '2026-09-01', '2026-09-14'),
    );
    $sprint = resolve(TaskPlanningCommand::class)->createSprint($sprintDraft, $sprintToken);

    $task = Task::query()->create([
        'workspace_id' => $this->workspaceId,
        'project_id' => $project->id,
        'sprint_id' => $sprint->id,
        'title' => 'Inspect pump',
        'created_by' => $this->actor->toArray(),
    ]);

    $timeDraft = new TimeLogDraft(
        taskId: (string) $task->getKey(),
        userId: $this->userId,
        minutes: 45,
        spentOn: '2026-09-10',
        comment: null,
        source: TimeLogSource::Manual,
        actor: $this->actor,
    );
    $timeToken = planningToken(
        requestKey: 'prim-08-time',
        payloadHash: PlanningHasher::timeLog($timeDraft, $this->context),
    );
    $time = resolve(TaskPlanningCommand::class)->recordTime($timeDraft, $timeToken);

    expect($project->type)->toBe('tsk.project')
        ->and($sprint->type)->toBe('tsk.sprint')
        ->and($time->type)->toBe('tsk.time-log')
        ->and(Task::query()->findOrFail($task->getKey())->spent_minutes)->toBe(45);
});

it('replays sprint creation without creating a second sprint', function (): void {
    $projectDraft = new ProjectDraft($this->workspaceId, 'Planning', $this->actor);
    $command = resolve(TaskPlanningCommand::class);
    $project = $command->createProject($projectDraft, planningToken('coverage-project', PlanningHasher::project($projectDraft, $this->context)));
    $draft = new SprintDraft($project->id, 'Sprint', startDate: '2026-10-01', endDate: '2026-10-14', actor: $this->actor);
    $token = planningToken('coverage-sprint', PlanningHasher::sprint($draft, $this->context, '2026-10-01', '2026-10-14'));
    $first = $command->createSprint($draft, $token);
    $replay = $command->createSprint($draft, $token);
    expect($replay)->toEqual($first)->and(Sprint::query()->count())->toBe(1);
});

it('rejects malformed planning tokens before writes', function (string $key, string $hash): void {
    $draft = new ProjectDraft($this->workspaceId, 'Planning', $this->actor);

    try {
        resolve(TaskPlanningCommand::class)->createProject($draft, planningToken($key, $hash));
        test()->fail('Invalid token was accepted.');
    } catch (InvalidPlanningDraft $exception) {
        expect($exception->errorDescriptor()->retryable)->toBeFalse();
    }
    expect(Project::query()->count())->toBe(0);
})->with([
    'empty key' => ['', str_repeat('a', 64)],
    'long key' => [str_repeat('a', 191), str_repeat('a', 64)],
    'malformed hash' => ['invalid-hash', 'BAD'],
    'different hash' => ['wrong-hash', str_repeat('a', 64)],
]);
