<?php

declare(strict_types=1);

namespace CoreX\Tasks\Support;

use CoreX\Tasks\Contracts\TaskAccess;
use CoreX\Tasks\Data\ActorData;
use CoreX\Tasks\Data\EntityReference;
use CoreX\Tasks\Data\MoveTaskToStageData;
use CoreX\Tasks\Data\TaskDraft;
use CoreX\Tenancy\TenantContext;
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
