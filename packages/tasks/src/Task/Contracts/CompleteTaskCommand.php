<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskReference;

interface CompleteTaskCommand
{
    public function handle(CompleteTaskData $data): TaskReference;
}
