<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskView;
use CoreX\Tasks\Task\Data\UpdateTaskData;
use Illuminate\Auth\Access\AuthorizationException;
use Packman\Attributes\Internal;

#[Internal]
final class DenyTaskMutationAccess implements TaskMutationAccess
{
    public function authorizeUpdate(UpdateTaskData $data, TenantContext $context, TaskView $task): void
    {
        throw new AuthorizationException('Не настроен авторизатор изменения задач.');
    }

    public function authorizeComplete(CompleteTaskData $data, TenantContext $context, TaskView $task): void
    {
        throw new AuthorizationException('Не настроен авторизатор завершения задач.');
    }
}
