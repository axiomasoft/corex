<?php

declare(strict_types=1);

namespace CoreX\Tasks\Support;

use CoreX\Tasks\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Data\ActorData;
use CoreX\Tasks\Data\PlanningOperation;
use CoreX\Tasks\Data\PlanningSubject;
use CoreX\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class DenyTaskPlanningAccess implements TaskPlanningAccess
{
    public function authorize(PlanningOperation $operation, PlanningSubject $subject, ActorData $actor, TenantContext $context): void
    {
        throw new AuthorizationException('Не настроен авторизатор планирования задач.');
    }
}
