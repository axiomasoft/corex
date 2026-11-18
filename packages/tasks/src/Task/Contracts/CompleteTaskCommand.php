<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskReference;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
interface CompleteTaskCommand
{
    public function handle(CompleteTaskData $data): TaskReference;
}
