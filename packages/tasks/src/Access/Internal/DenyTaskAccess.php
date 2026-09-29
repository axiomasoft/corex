<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\EntityReference;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskDraft;
use Illuminate\Auth\Access\AuthorizationException;

final class DenyTaskAccess implements TaskAccess
{
    public function authorizeCreate(TaskDraft $draft, TenantContext $context): void
    {
        throw new AuthorizationException('Не настроен авторизатор создания задач.');
    }

    public function authorizeMove(MoveTaskToStageData $data, TenantContext $context): void
    {
        throw new AuthorizationException('Не настроен авторизатор перемещения задач.');
    }

    public function authorizeTarget(EntityReference $target, ActorData $actor, TenantContext $context): void
    {
        throw new AuthorizationException('Не настроен авторизатор связанных сущностей задач.');
    }
}
