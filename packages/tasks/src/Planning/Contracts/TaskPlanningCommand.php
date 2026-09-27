<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\PlanningToken;
use CoreX\Tasks\Data\ProjectDraft;
use CoreX\Tasks\Data\ProjectReference;
use CoreX\Tasks\Data\SprintDraft;
use CoreX\Tasks\Data\SprintReference;
use CoreX\Tasks\Data\TimeLogDraft;
use CoreX\Tasks\Data\TimeLogReference;

interface TaskPlanningCommand
{
    public function createProject(ProjectDraft $draft, PlanningToken $token): ProjectReference;

    public function createSprint(SprintDraft $draft, PlanningToken $token): SprintReference;

    public function recordTime(TimeLogDraft $draft, PlanningToken $token): TimeLogReference;
}
