<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\UpdateTaskData;
use CoreX\Tasks\Task\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;

final class DenyTaskMutationAccess implements TaskMutationAccess
{
    public function authorizeUpdate(UpdateTaskData $data, TenantContext $context, Task $task): void
    {
        throw new AuthorizationException('Не настроен авторизатор изменения задач.');
    }

    public function authorizeComplete(CompleteTaskData $data, TenantContext $context, Task $task): void
    {
        throw new AuthorizationException('Не настроен авторизатор завершения задач.');
    }
}
