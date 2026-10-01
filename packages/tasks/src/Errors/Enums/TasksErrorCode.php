<?php

declare(strict_types=1);

namespace CoreX\Tasks\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum TasksErrorCode: string implements ErrorCode
{
    case InvalidPlanningDraft = 'tasks.invalid_planning_draft';
    case PlanningConflict = 'tasks.planning_conflict';
    case TasksModuleDisabled = 'tasks.tasks_module_disabled';

    public function value(): string
    {
        return $this->value;
    }
}
