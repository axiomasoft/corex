<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Tasks\Actions\AddTaskParticipant;
use CoreX\Tasks\Actions\CreateProcess;
use CoreX\Tasks\Actions\CreateTask;
use CoreX\Tasks\Actions\MoveTaskToStage;
use CoreX\Tasks\Enums\TaskParticipantRole;
use CoreX\Tasks\Enums\TaskPriority;
use CoreX\Tasks\Events\TaskCreated;
use CoreX\Tasks\Events\TaskMovedToStage;
use CoreX\Tasks\Models\Stage;
use CoreX\Tests\Stubs\Tenant;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
    $this->process = app(CreateProcess::class)->handle('Kanban', 'KANBAN');
    $this->todo = Stage::create(['process_id' => $this->process->id, 'name' => 'To do', 'position' => 0]);
    $this->done = Stage::create(['process_id' => $this->process->id, 'name' => 'Done', 'position' => 1, 'is_final' => true]);
});

it('creates a task at the first stage, tenant-stamped', function (): void {
    Event::fake([TaskCreated::class]);

    $task = app(CreateTask::class)->handle($this->process, 'Write docs', 'creator-1', priority: TaskPriority::High);

    expect($task->stage_id)->toBe($this->todo->id)
        ->and($task->tenant_id)->toBe('tenant-a')
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->task_key)->toStartWith('T-')
        ->and($task->archived_at)->toBeNull();

    Event::assertDispatched(TaskCreated::class);
});

it('archives a task when moved to a final stage', function (): void {
    Event::fake([TaskMovedToStage::class]);

    $task = app(CreateTask::class)->handle($this->process, 'T', 'c1');
    $moved = app(MoveTaskToStage::class)->handle($task, $this->done);

    expect($moved->stage_id)->toBe($this->done->id)
        ->and($moved->archived_at)->not->toBeNull();

    Event::assertDispatched(TaskMovedToStage::class);
});

it('adds a task participant', function (): void {
    $task = app(CreateTask::class)->handle($this->process, 'T', 'c1');
    app(AddTaskParticipant::class)->handle($task, 'u2', TaskParticipantRole::Reviewer);

    expect($task->participants()->count())->toBe(1)
        ->and($task->participants()->first()?->role)->toBe(TaskParticipantRole::Reviewer);
});

it('refuses to create a task in a process without stages', function (): void {
    $empty = app(CreateProcess::class)->handle('Empty', 'EMPTY');

    app(CreateTask::class)->handle($empty, 'orphan', 'c1');
})->throws(RuntimeException::class);
