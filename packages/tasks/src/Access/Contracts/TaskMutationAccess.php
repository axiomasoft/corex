<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Data\UpdateTaskData;
use CoreX\Tasks\Models\Task;
use CoreX\Tenancy\TenantContext;

interface TaskMutationAccess
{
    public function authorizeUpdate(UpdateTaskData $data, TenantContext $context, Task $task): void;

    public function authorizeComplete(CompleteTaskData $data, TenantContext $context, Task $task): void;
}
