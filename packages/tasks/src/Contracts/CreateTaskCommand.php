<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\TaskDraft;
use CoreX\Tasks\Data\TaskReference;

interface CreateTaskCommand
{
    public function handle(TaskDraft $draft): TaskReference;
}
