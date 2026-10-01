<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Planning\Data\PlanningOperation;
use CoreX\Tasks\Planning\Data\PlanningSubject;
use CoreX\Tasks\Task\Data\ActorData;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TaskPlanningAccess
{
    public function authorize(PlanningOperation $operation, PlanningSubject $subject, ActorData $actor, TenantContext $context): void;
}
