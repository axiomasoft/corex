<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Planning\Data\PlanningOperation;
use CoreX\Tasks\Planning\Data\PlanningSubject;
use CoreX\Tasks\Task\Data\ActorData;

interface TaskPlanningAccess
{
    public function authorize(PlanningOperation $operation, PlanningSubject $subject, ActorData $actor, TenantContext $context): void;
}
