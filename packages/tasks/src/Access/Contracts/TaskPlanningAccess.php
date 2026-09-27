<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\ActorData;
use CoreX\Tasks\Data\PlanningOperation;
use CoreX\Tasks\Data\PlanningSubject;
use CoreX\Tenancy\TenantContext;

interface TaskPlanningAccess
{
    public function authorize(PlanningOperation $operation, PlanningSubject $subject, ActorData $actor, TenantContext $context): void;
}
