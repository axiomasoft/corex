<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskReference;

interface CreateTaskCommand
{
    public function handle(TaskDraft $draft): TaskReference;
}
