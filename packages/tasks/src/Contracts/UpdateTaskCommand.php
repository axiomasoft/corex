<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\TaskReference;
use CoreX\Tasks\Data\UpdateTaskData;

interface UpdateTaskCommand
{
    public function handle(UpdateTaskData $data): TaskReference;
}
