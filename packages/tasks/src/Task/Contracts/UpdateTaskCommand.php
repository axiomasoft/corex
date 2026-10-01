<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Attributes\Api;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Data\UpdateTaskData;

#[Api(since: '1.0')]
interface UpdateTaskCommand
{
    public function handle(UpdateTaskData $data): TaskReference;
}
