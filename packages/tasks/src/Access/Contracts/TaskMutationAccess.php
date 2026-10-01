<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskView;
use CoreX\Tasks\Task\Data\UpdateTaskData;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TaskMutationAccess
{
    public function authorizeUpdate(UpdateTaskData $data, TenantContext $context, TaskView $task): void;

    public function authorizeComplete(CompleteTaskData $data, TenantContext $context, TaskView $task): void;
}
