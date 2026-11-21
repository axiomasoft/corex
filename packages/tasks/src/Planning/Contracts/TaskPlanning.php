<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Contracts;

use CoreX\Tasks\Planning\Data\PlanningToken;
use CoreX\Tasks\Project\Data\ProjectDraft;
use CoreX\Tasks\Project\Data\ProjectReference;
use CoreX\Tasks\Sprint\Data\SprintDraft;
use CoreX\Tasks\Sprint\Data\SprintReference;
use CoreX\Tasks\Time\Data\TimeLogDraft;
use CoreX\Tasks\Time\Data\TimeLogReference;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TaskPlanning
{
    public function createProject(ProjectDraft $draft, PlanningToken $token): ProjectReference;

    public function createSprint(SprintDraft $draft, PlanningToken $token): SprintReference;

    public function recordTime(TimeLogDraft $draft, PlanningToken $token): TimeLogReference;
}
