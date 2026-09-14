<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\ActorData;
use CoreX\Tasks\Data\EntityReference;
use CoreX\Tasks\Data\MoveTaskToStageData;
use CoreX\Tasks\Data\TaskDraft;
use CoreX\Tenancy\TenantContext;

interface TaskAccess
{
    public function authorizeCreate(TaskDraft $draft, TenantContext $context): void;

    public function authorizeMove(MoveTaskToStageData $data, TenantContext $context): void;

    public function authorizeTarget(EntityReference $target, ActorData $actor, TenantContext $context): void;
}
