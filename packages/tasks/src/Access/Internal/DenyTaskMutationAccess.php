<?php

declare(strict_types=1);

namespace CoreX\Tasks\Support;

use CoreX\Tasks\Contracts\TaskMutationAccess;
use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Data\UpdateTaskData;
use CoreX\Tasks\Models\Task;
use CoreX\Tenancy\TenantContext;
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
