<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\CompleteTaskData;
use CoreX\Tasks\Data\TaskReference;

interface CompleteTaskCommand
{
    public function handle(CompleteTaskData $data): TaskReference;
}
