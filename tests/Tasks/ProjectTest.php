<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Tasks\Actions\CreateProject;
use CoreX\Tasks\Enums\ProjectParticipantRole;
use CoreX\Tasks\Enums\ProjectStatus;
use CoreX\Tasks\Events\ProjectCreated;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\ProjectParticipant;
use CoreX\Tests\Stubs\Tenant;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
});

it('creates an active project with a participant', function (): void {
    Event::fake([ProjectCreated::class]);

    $project = app(CreateProject::class)->handle('Q3 Campaign', 'Q3');

    expect($project->status)->toBe(ProjectStatus::Active)
        ->and($project->tenant_id)->toBe('tenant-a');

    Event::assertDispatched(ProjectCreated::class);

    ProjectParticipant::create(['project_id' => $project->id, 'user_id' => 'u1', 'role' => ProjectParticipantRole::Lead]);
    expect($project->participants()->count())->toBe(1);
});

it('isolates projects by tenant', function (): void {
    app(CreateProject::class)->handle('A', 'A');

    app(TenantService::class)->setCurrent(new Tenant('tenant-b'));
    expect(Project::query()->count())->toBe(0);

    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
    expect(Project::query()->count())->toBe(1);
});
