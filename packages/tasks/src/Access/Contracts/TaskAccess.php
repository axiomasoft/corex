<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\EntityReference;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskDraft;

interface TaskAccess
{
    public function authorizeCreate(TaskDraft $draft, TenantContext $context): void;

    public function authorizeMove(MoveTaskToStageData $data, TenantContext $context): void;

    public function authorizeTarget(EntityReference $target, ActorData $actor, TenantContext $context): void;
}
