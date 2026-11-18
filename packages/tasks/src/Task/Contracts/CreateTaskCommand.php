<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskReference;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
interface CreateTaskCommand
{
    public function handle(TaskDraft $draft): TaskReference;
}
