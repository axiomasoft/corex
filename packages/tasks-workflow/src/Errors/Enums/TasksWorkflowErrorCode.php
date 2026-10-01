<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum TasksWorkflowErrorCode: string implements ErrorCode
{
    case MaterializationConflict = 'tasks-workflow.materialization_conflict';
    case StaleMaterialization = 'tasks-workflow.stale_materialization';
    case UnsupportedMaterialization = 'tasks-workflow.unsupported_materialization';

    public function value(): string
    {
        return $this->value;
    }
}
