<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskReference;

interface MoveTaskToStageCommand
{
    public function handle(MoveTaskToStageData $data): TaskReference;
}
