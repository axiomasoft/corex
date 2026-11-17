<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum RecurringTasksErrorCode: string implements ErrorCode
{
    case MaterializationConflict = 'recurring-tasks.materialization_conflict';
    case StaleMaterialization = 'recurring-tasks.stale_materialization';
    case UnsupportedMaterialization = 'recurring-tasks.unsupported_materialization';

    public function value(): string
    {
        return $this->value;
    }
}
