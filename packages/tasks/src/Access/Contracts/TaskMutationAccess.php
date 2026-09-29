<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\UpdateTaskData;
use CoreX\Tasks\Task\Models\Task;

interface TaskMutationAccess
{
    public function authorizeUpdate(UpdateTaskData $data, TenantContext $context, Task $task): void;

    public function authorizeComplete(CompleteTaskData $data, TenantContext $context, Task $task): void;
}
