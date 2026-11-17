<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Access\Exceptions\TasksModuleDisabled;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Sprint\Internal\Models\Sprint;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Task\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\EntityReference;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskView;
use CoreX\Tasks\Task\Data\UpdateTaskData;
use CoreX\Tasks\Task\Enums\TaskPriority;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\Task\Events\TaskCompleted;
use CoreX\Tasks\Task\Events\TaskMovedToStage;
use CoreX\Tasks\Task\Events\TaskUpdated;
use CoreX\Tasks\Task\Internal\Models\Stage;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\TasksModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

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
    $this->project = Project::query()->create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Launch',
        'created_by' => JsonDocuments::decodeTree($this->actor->toArray()),
    ]);
    $this->todo = Stage::query()->create([
        'project_id' => $this->project->getKey(),
        'code' => 'todo',
        'name' => ['ru' => 'К работе'],
    ]);
    $this->done = Stage::query()->create([
        'project_id' => $this->project->getKey(),
        'code' => 'done',
        'name' => ['ru' => 'Готово'],
        'auto_status' => TaskStatus::Done,
    ]);
});

it('creates a UUIDv7 task and portable target references through the public command', function (): void {
    $targetId = (string) Str::uuid7();

    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: '  Подготовить предложение  ',
        actor: $this->actor,
        priority: TaskPriority::High,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->todo->getKey(),
        assigneeId: $this->userId,
        targets: [new EntityReference(type: 'crm.company', id: $targetId)],
    ));

    $task = Task::query()->findOrFail($reference->id);

    expect($reference->type)->toBe('tsk.task')
        ->and($reference->display)->toBe('Подготовить предложение')
        ->and($reference->id[14])->toBe('7')
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->author_id)->toBe($this->userId)
        ->and($task->targets()->first()?->entity_type)->toBe('crm.company')
        ->and($task->targets()->first()?->entity_id)->toBe($targetId);
});

it('participates in an outer tenant transaction', function (): void {
    DB::beginTransaction();

    resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Transactional task',
        actor: $this->actor,
    ));

    expect(Task::query()->count())->toBe(1);

    DB::rollBack();

    expect(Task::query()->count())->toBe(0);
});

it('rejects a stage from another project without a partial write', function (): void {
    $otherProject = Project::query()->create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Other project',
        'created_by' => $this->actor->toArray(),
    ]);
    $foreignStage = Stage::query()->create([
        'project_id' => $otherProject->getKey(),
        'code' => 'foreign',
        'name' => ['ru' => 'Чужая'],
    ]);

    expect(fn () => resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Invalid task',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $foreignStage->getKey(),
    )))->toThrow(ValidationException::class);

    expect(Task::query()->count())->toBe(0);
});

it('rejects a workspace outside the narrowed tenant context', function (): void {
    $current = resolve(TenantContextResolver::class)->current();
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->once()->andReturn(new TenantContext(
        account: $current->account,
        workspace: new WorkspaceRef(
            id: $this->otherWorkspaceId,
            slug: 'other',
            type: 'branch',
            path: 'other',
            parentId: null,
        ),
    ));
    app()->instance(TenantContextResolver::class, $resolver);

    expect(fn () => resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Wrong workspace',
        actor: $this->actor,
    )))->toThrow(ValidationException::class);

    expect(Task::query()->count())->toBe(0);
});

it('cannot move a task id that is absent from the current account database', function (): void {
    expect(fn () => resolve(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(
        taskId: (string) Str::uuid7(),
        stageId: (string) $this->done->getKey(),
        actor: $this->actor,
    )))->toThrow(ValidationException::class);
});

it('cannot move a task from another narrowed workspace', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Keep isolated',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->todo->getKey(),
    ));
    $current = resolve(TenantContextResolver::class)->current();
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->once()->andReturn(new TenantContext(
        account: $current->account,
        workspace: new WorkspaceRef(
            id: $this->otherWorkspaceId,
            slug: 'other',
            type: 'branch',
            path: 'other',
            parentId: null,
        ),
    ));
    app()->instance(TenantContextResolver::class, $resolver);

    expect(fn () => resolve(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(
        taskId: $reference->id,
        stageId: (string) $this->done->getKey(),
        actor: $this->actor,
    )))->toThrow(ValidationException::class);

    expect(Task::query()->findOrFail($reference->id)->stage_id)->toBe($this->todo->getKey());
});

it('moves only within the task project and applies the stage status', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Move me',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->todo->getKey(),
    ));

    resolve(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(
        taskId: $reference->id,
        stageId: (string) $this->done->getKey(),
        actor: $this->actor,
    ));

    $task = Task::query()->findOrFail($reference->id);

    expect($task->stage_id)->toBe($this->done->getKey())
        ->and($task->status)->toBe(TaskStatus::Done)
        ->and($task->completed_at)->not->toBeNull();
});

it('enforces completion authorization when moving to a Done stage', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(workspaceId: $this->workspaceId, title: 'Protected', actor: $this->actor, projectId: $this->project->id, stageId: $this->todo->id));
    $access = Mockery::mock(TaskMutationAccess::class);
    $access->shouldReceive('authorizeComplete')->once()->andThrow(new AuthorizationException('completion denied'));
    app()->instance(TaskMutationAccess::class, $access);
    expect(fn () => resolve(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(taskId: $reference->id, stageId: $this->done->id, actor: $this->actor)))
        ->toThrow(AuthorizationException::class, 'completion denied');
    expect(Task::query()->findOrFail($reference->id)->stage_id)->toBe($this->todo->id);
});

it('completes once through move and preserves the completion time on retry', function (): void {
    Event::fake([TaskCompleted::class, TaskMovedToStage::class]);
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(workspaceId: $this->workspaceId, title: 'Once', actor: $this->actor, projectId: $this->project->id, stageId: $this->todo->id));
    $move = new MoveTaskToStageData(taskId: $reference->id, stageId: $this->done->id, actor: $this->actor);
    resolve(MoveTaskToStageCommand::class)->handle($move);
    $completed = Task::query()->findOrFail($reference->id)->completed_at;
    $this->travel(1)->minutes();
    resolve(MoveTaskToStageCommand::class)->handle($move);
    expect(Task::query()->findOrFail($reference->id)->completed_at->equalTo($completed))->toBeTrue();
    Event::assertDispatchedTimes(TaskCompleted::class, 1);
    Event::assertDispatchedTimes(TaskMovedToStage::class, 1);
});

it('cannot complete a cancelled task by moving it', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(workspaceId: $this->workspaceId, title: 'Cancelled', actor: $this->actor, projectId: $this->project->id, stageId: $this->todo->id));
    Task::query()->whereKey($reference->id)->update(['status' => TaskStatus::Cancelled]);
    expect(fn () => resolve(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(taskId: $reference->id, stageId: $this->done->id, actor: $this->actor)))
        ->toThrow(ValidationException::class);
});

it('updates editable task fields through the public command', function (): void {
    Event::fake([TaskUpdated::class]);
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Initial title',
        actor: $this->actor,
    ));

    resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData(
        taskId: $reference->id,
        actor: $this->actor,
        provided: ['title', 'priority', 'assignee_id', 'estimate_minutes'],
        title: '  Updated title  ',
        priority: TaskPriority::Urgent,
        assigneeId: $this->userId,
        estimateMinutes: 45,
    ));

    $task = Task::query()->findOrFail($reference->id);

    expect($task->title)->toBe('Updated title')
        ->and($task->priority)->toBe(TaskPriority::Urgent)
        ->and($task->assignee_id)->toBe($this->userId)
        ->and($task->estimate_minutes)->toBe(45);
    Event::assertDispatchedTimes(TaskUpdated::class, 1);
});

it('changes a task project through the public command and resets project-bound fields', function (): void {
    $otherProject = Project::query()->create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Other project',
        'created_by' => $this->actor->toArray(),
    ]);
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Move between projects',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->todo->getKey(),
    ));

    resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData(
        taskId: $reference->id,
        actor: $this->actor,
        provided: ['project_id'],
        projectId: (string) $otherProject->getKey(),
    ));

    $task = Task::query()->findOrFail($reference->id);

    expect($task->project_id)->toBe($otherProject->getKey())
        ->and($task->stage_id)->toBeNull()
        ->and($task->sprint_id)->toBeNull();
});

it('rejects a project outside the task workspace during update', function (): void {
    $foreignProject = Project::query()->create([
        'workspace_id' => $this->otherWorkspaceId,
        'name' => 'Foreign project',
        'created_by' => $this->actor->toArray(),
    ]);
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Keep project isolated',
        actor: $this->actor,
    ));

    expect(fn () => resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData(
        taskId: $reference->id,
        actor: $this->actor,
        provided: ['project_id'],
        projectId: (string) $foreignProject->getKey(),
    )))->toThrow(ValidationException::class);

    expect(Task::query()->findOrFail($reference->id)->project_id)->toBeNull();
});

it('authorizes mutations against the task inside its locking transaction', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Authorize locked task',
        actor: $this->actor,
        assigneeId: $this->userId,
    ));
    $mutationAccess = Mockery::mock(TaskMutationAccess::class);
    $assertLockedTask = (fn (mixed $data, TenantContext $context, TaskView $task): bool => $data->taskId === $reference->id
        && $context->account->status === 'active'
        && $task->id === $reference->id
        && DB::connection()->transactionLevel() > 0);

    $mutationAccess->shouldReceive('authorizeUpdate')
        ->once()
        ->withArgs($assertLockedTask)
        ->andReturnNull();
    $mutationAccess->shouldReceive('authorizeComplete')
        ->once()
        ->withArgs($assertLockedTask)
        ->andReturnNull();
    app()->instance(TaskMutationAccess::class, $mutationAccess);

    resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData(
        taskId: $reference->id,
        actor: $this->actor,
        provided: ['title'],
        title: 'Authorization stayed atomic',
    ));
    resolve(CompleteTaskCommand::class)->handle(new CompleteTaskData(
        taskId: $reference->id,
        actor: $this->actor,
    ));

    expect(Task::query()->findOrFail($reference->id)->status)->toBe(TaskStatus::Done);
});

it('rolls an update back with its outer tenant transaction', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Before rollback',
        actor: $this->actor,
    ));

    DB::beginTransaction();

    resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData(
        taskId: $reference->id,
        actor: $this->actor,
        provided: ['title'],
        title: 'After rollback',
    ));

    expect(Task::query()->findOrFail($reference->id)->title)->toBe('After rollback');

    DB::rollBack();

    expect(Task::query()->findOrFail($reference->id)->title)->toBe('Before rollback');
});

it('completes a task atomically and does not emit a duplicate event', function (): void {
    Event::fake([TaskCompleted::class]);
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Complete me',
        actor: $this->actor,
    ));
    $data = new CompleteTaskData(
        taskId: $reference->id,
        actor: $this->actor,
    );

    resolve(CompleteTaskCommand::class)->handle($data);
    resolve(CompleteTaskCommand::class)->handle($data);

    $task = Task::query()->findOrFail($reference->id);

    expect($task->status)->toBe(TaskStatus::Done)
        ->and($task->completed_at)->not->toBeNull();
    Event::assertDispatchedTimes(TaskCompleted::class, 1);
});

it('does not complete a cancelled task', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Cancelled task',
        actor: $this->actor,
    ));
    Task::query()->whereKey($reference->id)->update(['status' => TaskStatus::Cancelled]);

    expect(fn () => resolve(CompleteTaskCommand::class)->handle(new CompleteTaskData(
        taskId: $reference->id,
        actor: $this->actor,
    )))->toThrow(ValidationException::class);

    expect(Task::query()->findOrFail($reference->id)->completed_at)->toBeNull();
});

it('denies commands when the module is inactive', function (): void {
    $modules = Mockery::mock(ModuleRegistry::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isActive')->once()->andReturnFalse();
    });
    app()->instance(ModuleRegistry::class, $modules);

    expect(fn () => resolve(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Disabled',
        actor: $this->actor,
    )))->toThrow(TasksModuleDisabled::class);
});

it('ships a deny-by-default host authorization seam', function (): void {
    app()->forgetInstance(TaskAccess::class);

    expect(fn () => resolve(TaskAccess::class)->authorizeCreate(
        draft: new TaskDraft(
            workspaceId: $this->workspaceId,
            title: 'Denied',
            actor: $this->actor,
        ),
        context: resolve(TenantContextResolver::class)->current(),
    ))->toThrow(AuthorizationException::class);

    app()->forgetInstance(TaskMutationAccess::class);

    expect(fn () => resolve(TaskMutationAccess::class)->authorizeComplete(
        data: new CompleteTaskData(
            taskId: (string) Str::uuid7(),
            actor: $this->actor,
        ),
        context: resolve(TenantContextResolver::class)->current(),
        task: new TaskView(
            id: (string) Str::uuid7(), workspaceId: null, projectId: null, stageId: null,
            title: 'Denied', status: TaskStatus::Open, authorId: null, assigneeId: null,
            requiresAcceptance: false,
        ),
    ))->toThrow(AuthorizationException::class);
});

it('publishes the lifecycle manifest instead of loading migrations from its provider', function (): void {
    $manifest = (new TasksModule)->manifest();
    $provider = file_get_contents(__DIR__.'/../../src/TasksServiceProvider.php');
    $composer = json_decode(
        json: (string) file_get_contents(__DIR__.'/../../composer.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest->composerName)->toBe(TasksModule::NAME)
        ->and($manifest->tablePrefix)->toBe('tsk')
        ->and($composer['type'])->toBe('packman-module')
        ->and($composer['extra']['packman']['module'])->toBe(TasksModule::class)
        ->and($provider)->not->toContain('loadMigrationsFrom');
});

it('rejects invalid task drafts atomically', function (array $changes, string $field): void {
    $values = array_replace(['workspaceId' => $this->workspaceId, 'title' => 'Valid', 'actor' => $this->actor], $changes);

    try {
        resolve(CreateTaskCommand::class)->handle(new TaskDraft(...$values));
        test()->fail('Invalid draft was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
    expect(Task::query()->count())->toBe(0);
})->with([
    'blank title' => [['title' => '  '], 'title'],
    'long title' => [['title' => str_repeat('a', 256)], 'title'],
    'missing workspace' => [['workspaceId' => 'missing'], 'workspace_id'],
    'negative estimate' => [['estimateMinutes' => -1], 'estimate_minutes'],
    'reversed dates' => [['startsAt' => new DateTimeImmutable('2026-10-02'), 'dueAt' => new DateTimeImmutable('2026-10-01')], 'due_at'],
    'unknown author' => [['authorId' => 'missing'], 'author_id'],
    'unknown project' => [['projectId' => 'missing'], 'project_id'],
    'unknown sprint' => [['sprintId' => 'missing'], 'sprint_id'],
    'unknown parent' => [['parentId' => 'missing'], 'parent_id'],
    'invalid target' => [['targets' => [new EntityReference('', 'missing')]], 'targets'],
    'duplicate target' => [['targets' => [new EntityReference('item', '11111111-1111-4111-8111-111111111111'), new EntityReference('item', '11111111-1111-4111-8111-111111111111')]], 'targets'],
]);

it('rejects invalid updates without changing the persisted task', function (array $changes, string $field): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft($this->workspaceId, 'Original', $this->actor));
    $values = array_replace(['taskId' => $reference->id, 'actor' => $this->actor, 'provided' => []], $changes);

    try {
        resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData(...$values));
        test()->fail('Invalid update was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
    expect(Task::query()->findOrFail($reference->id)->title)->toBe('Original');
})->with([
    'no fields' => [[], 'task'],
    'unknown field' => [['provided' => ['unsupported']], 'task'],
    'duplicate field' => [['provided' => ['title', 'title'], 'title' => 'Changed'], 'task'],
    'missing task' => [['taskId' => 'missing', 'provided' => ['title'], 'title' => 'Changed'], 'task_id'],
    'blank title' => [['provided' => ['title'], 'title' => ' '], 'title'],
    'null priority' => [['provided' => ['priority']], 'priority'],
    'missing assignee' => [['provided' => ['assignee_id'], 'assigneeId' => 'missing'], 'assignee_id'],
    'negative estimate' => [['provided' => ['estimate_minutes'], 'estimateMinutes' => -1], 'estimate_minutes'],
    'reversed dates' => [['provided' => ['starts_at', 'due_at'], 'startsAt' => new DateTimeImmutable('2026-10-02'), 'dueAt' => new DateTimeImmutable('2026-10-01')], 'due_at'],
]);

it('updates dates description and nullable fields and emits no event for a no-op', function (): void {
    $reference = resolve(CreateTaskCommand::class)->handle(new TaskDraft($this->workspaceId, 'Original', $this->actor));
    $starts = new DateTimeImmutable('2026-10-01T12:00:00Z');
    $due = new DateTimeImmutable('2026-10-02T12:00:00Z');
    resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData($reference->id, $this->actor,
        ['description', 'starts_at', 'due_at'], description: 'Details', startsAt: $starts, dueAt: $due));
    Event::fake([TaskUpdated::class]);
    resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData($reference->id, $this->actor, ['title'], title: 'Original'));
    Event::assertNotDispatched(TaskUpdated::class);
    resolve(UpdateTaskCommand::class)->handle(new UpdateTaskData($reference->id, $this->actor, ['assignee_id', 'project_id']));
    $task = Task::query()->findOrFail($reference->id);
    expect($task->description)->toBe('Details')->and($task->starts_at->format('c'))->toBe($starts->format('c'))
        ->and($task->due_at->format('c'))->toBe($due->format('c'))->and($task->project_id)->toBeNull();
});

it('resolves task project stage parent and child relationships on the current tenant connection', function (): void {
    $sprint = Sprint::query()->create([
        'project_id' => $this->project->getKey(), 'name' => 'Sprint', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-14',
    ]);
    $parent = resolve(CreateTaskCommand::class)->handle(new TaskDraft($this->workspaceId, 'Parent', $this->actor,
        projectId: (string) $this->project->getKey(), sprintId: (string) $sprint->getKey(), stageId: (string) $this->todo->getKey()));
    $child = resolve(CreateTaskCommand::class)->handle(new TaskDraft($this->workspaceId, 'Child', $this->actor, parentId: $parent->id));
    $task = Task::query()->findOrFail($parent->id);
    expect($task->project->getKey())->toBe($this->project->getKey())->and($task->sprint->getKey())->toBe($sprint->getKey())
        ->and($task->stage->getKey())->toBe($this->todo->getKey())->and($task->children->first()->getKey())->toBe($child->id)
        ->and($this->project->tasks->first()->getKey())->toBe($parent->id)->and($this->project->sprints->first()->getKey())->toBe($sprint->getKey())
        ->and($this->project->stages)->toHaveCount(2)->and($sprint->project->getKey())->toBe($this->project->getKey())
        ->and($sprint->tasks->first()->getKey())->toBe($parent->id)->and($this->todo->project->getKey())->toBe($this->project->getKey())
        ->and($this->todo->tasks->first()->getKey())->toBe($parent->id)->and(Task::query()->findOrFail($child->id)->parent->getKey())->toBe($parent->id)
        ->and($task->watchers)->toHaveCount(0)->and($task->timeLogs)->toHaveCount(0);
    expect(fn () => resolve(CreateTaskCommand::class)->handle(new TaskDraft($this->workspaceId, 'Grandchild', $this->actor, parentId: $child->id)))
        ->toThrow(ValidationException::class);
    expect(Task::query()->count())->toBe(2);
});
