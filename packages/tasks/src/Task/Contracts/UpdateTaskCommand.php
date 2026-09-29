<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Data\UpdateTaskData;

interface UpdateTaskCommand
{
    public function handle(UpdateTaskData $data): TaskReference;
}
