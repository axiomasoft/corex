<?php

declare(strict_types=1);

namespace CoreX\Tasks\Contracts;

use CoreX\Tasks\Data\MoveTaskToStageData;
use CoreX\Tasks\Data\TaskReference;

interface MoveTaskToStageCommand
{
    public function handle(MoveTaskToStageData $data): TaskReference;
}
