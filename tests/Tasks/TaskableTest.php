<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Tasks\Actions\CreateProcess;
use CoreX\Tasks\Actions\CreateProject;
use CoreX\Tasks\Actions\CreateTask;
use CoreX\Tasks\Models\Stage;
use CoreX\Tests\Stubs\Tenant;

beforeEach(function (): void {
    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
});

it('attaches a task to a polymorphic subject resolved via the morph alias', function (): void {
    $process = app(CreateProcess::class)->handle('P', 'P1');
    Stage::create(['process_id' => $process->id, 'name' => 'S', 'position' => 0]);
    $project = app(CreateProject::class)->handle('Website', 'WEB');

    $task = app(CreateTask::class)->handle($process, 'Design', 'c1', taskable: $project);

    expect($task->taskable_type)->toBe('tsk_project') // morph alias from EntityRegistry, not FQCN
        ->and($task->taskable->is($project))->toBeTrue();
});
