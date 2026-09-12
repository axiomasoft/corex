<?php

declare(strict_types=1);

use CoreX\Enums\ActorType;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tasks\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Contracts\CreateTaskCommand;
use CoreX\Tasks\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Contracts\TaskMutationAccess;
use CoreX\Tasks\Contracts\UpdateTaskCommand;
use CoreX\Tasks\Data\ActorData;
use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Data\EntityReference;
use CoreX\Tasks\Data\MoveTaskToStageData;
use CoreX\Tasks\Data\TaskDraft;
use CoreX\Tasks\Data\UpdateTaskData;
use CoreX\Tasks\Enums\TaskPriority;
use CoreX\Tasks\Enums\TaskStatus;
use CoreX\Tasks\Events\TaskCompleted;
use CoreX\Tasks\Events\TaskUpdated;
use CoreX\Tasks\Exceptions\TasksModuleDisabled;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\Stage;
use CoreX\Tasks\Models\Task;
use CoreX\Tasks\TasksModule;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use CoreX\Tenancy\WorkspaceRef;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

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
        'created_by' => $this->actor->toArray(),
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

    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
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

    app(CreateTaskCommand::class)->handle(new TaskDraft(
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

    expect(fn () => app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Invalid task',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $foreignStage->getKey(),
    )))->toThrow(ValidationException::class);

    expect(Task::query()->count())->toBe(0);
});

it('rejects a workspace outside the narrowed tenant context', function (): void {
    $current = app(TenantContextResolver::class)->current();
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

    expect(fn () => app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Wrong workspace',
        actor: $this->actor,
    )))->toThrow(ValidationException::class);

    expect(Task::query()->count())->toBe(0);
});

it('cannot move a task id that is absent from the current account database', function (): void {
    expect(fn () => app(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(
        taskId: (string) Str::uuid7(),
        stageId: (string) $this->done->getKey(),
        actor: $this->actor,
    )))->toThrow(ValidationException::class);
});

it('cannot move a task from another narrowed workspace', function (): void {
    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Keep isolated',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->todo->getKey(),
    ));
    $current = app(TenantContextResolver::class)->current();
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

    expect(fn () => app(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(
        taskId: $reference->id,
        stageId: (string) $this->done->getKey(),
        actor: $this->actor,
    )))->toThrow(ValidationException::class);

    expect(Task::query()->findOrFail($reference->id)->stage_id)->toBe($this->todo->getKey());
});

it('moves only within the task project and applies the stage status', function (): void {
    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Move me',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->todo->getKey(),
    ));

    app(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData(
        taskId: $reference->id,
        stageId: (string) $this->done->getKey(),
        actor: $this->actor,
    ));

    $task = Task::query()->findOrFail($reference->id);

    expect($task->stage_id)->toBe($this->done->getKey())
        ->and($task->status)->toBe(TaskStatus::Done)
        ->and($task->completed_at)->not->toBeNull();
});

it('updates editable task fields through the public command', function (): void {
    Event::fake([TaskUpdated::class]);
    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Initial title',
        actor: $this->actor,
    ));

    app(UpdateTaskCommand::class)->handle(new UpdateTaskData(
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

it('authorizes mutations against the task inside its locking transaction', function (): void {
    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Authorize locked task',
        actor: $this->actor,
        assigneeId: $this->userId,
    ));
    $mutationAccess = Mockery::mock(TaskMutationAccess::class);
    $assertLockedTask = function (mixed $data, TenantContext $context, Task $task) use ($reference): bool {
        return $data->taskId === $reference->id
            && $context->account->status === 'active'
            && $task->getKey() === $reference->id
            && $task->getConnection()->transactionLevel() > 0;
    };

    $mutationAccess->shouldReceive('authorizeUpdate')
        ->once()
        ->withArgs($assertLockedTask)
        ->andReturnNull();
    $mutationAccess->shouldReceive('authorizeComplete')
        ->once()
        ->withArgs($assertLockedTask)
        ->andReturnNull();
    app()->instance(TaskMutationAccess::class, $mutationAccess);

    app(UpdateTaskCommand::class)->handle(new UpdateTaskData(
        taskId: $reference->id,
        actor: $this->actor,
        provided: ['title'],
        title: 'Authorization stayed atomic',
    ));
    app(CompleteTaskCommand::class)->handle(new CompleteTaskData(
        taskId: $reference->id,
        actor: $this->actor,
    ));

    expect(Task::query()->findOrFail($reference->id)->status)->toBe(TaskStatus::Done);
});

it('rolls an update back with its outer tenant transaction', function (): void {
    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Before rollback',
        actor: $this->actor,
    ));

    DB::beginTransaction();

    app(UpdateTaskCommand::class)->handle(new UpdateTaskData(
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
    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Complete me',
        actor: $this->actor,
    ));
    $data = new CompleteTaskData(
        taskId: $reference->id,
        actor: $this->actor,
    );

    app(CompleteTaskCommand::class)->handle($data);
    app(CompleteTaskCommand::class)->handle($data);

    $task = Task::query()->findOrFail($reference->id);

    expect($task->status)->toBe(TaskStatus::Done)
        ->and($task->completed_at)->not->toBeNull();
    Event::assertDispatchedTimes(TaskCompleted::class, 1);
});

it('does not complete a cancelled task', function (): void {
    $reference = app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Cancelled task',
        actor: $this->actor,
    ));
    Task::query()->whereKey($reference->id)->update(['status' => TaskStatus::Cancelled]);

    expect(fn () => app(CompleteTaskCommand::class)->handle(new CompleteTaskData(
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

    expect(fn () => app(CreateTaskCommand::class)->handle(new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Disabled',
        actor: $this->actor,
    )))->toThrow(TasksModuleDisabled::class);
});

it('ships a deny-by-default host authorization seam', function (): void {
    app()->forgetInstance(TaskAccess::class);

    expect(fn () => app(TaskAccess::class)->authorizeCreate(
        draft: new TaskDraft(
            workspaceId: $this->workspaceId,
            title: 'Denied',
            actor: $this->actor,
        ),
        context: app(TenantContextResolver::class)->current(),
    ))->toThrow(AuthorizationException::class);

    app()->forgetInstance(TaskMutationAccess::class);

    expect(fn () => app(TaskMutationAccess::class)->authorizeComplete(
        data: new CompleteTaskData(
            taskId: (string) Str::uuid7(),
            actor: $this->actor,
        ),
        context: app(TenantContextResolver::class)->current(),
        task: new Task,
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
        ->and($composer['type'])->toBe('corex-module')
        ->and($composer['extra']['corex']['module'])->toBe(TasksModule::class)
        ->and($provider)->not->toContain('loadMigrationsFrom');
});
