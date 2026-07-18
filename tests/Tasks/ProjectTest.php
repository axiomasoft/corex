<?php

declare(strict_types=1);

use CoreX\Tasks\Actions\CreateProject;
use CoreX\Tasks\Enums\ProjectParticipantRole;
use CoreX\Tasks\Enums\ProjectStatus;
use CoreX\Tasks\Events\ProjectCreated;
use CoreX\Tasks\Models\ProjectParticipant;
use Illuminate\Support\Facades\Event;

it('creates an active project with a participant', function (): void {
    Event::fake([ProjectCreated::class]);

    $project = app(CreateProject::class)->handle('Q3 Campaign', 'Q3');

    expect($project->status)->toBe(ProjectStatus::Active);

    Event::assertDispatched(ProjectCreated::class);

    ProjectParticipant::create(['project_id' => $project->id, 'user_id' => 'u1', 'role' => ProjectParticipantRole::Lead]);
    expect($project->participants()->count())->toBe(1);
});
