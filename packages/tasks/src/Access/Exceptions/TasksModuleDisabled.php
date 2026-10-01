<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tasks\Errors\Enums\TasksErrorCode;
use DomainException;

#[Api(since: '1.0')]
final class TasksModuleDisabled extends DomainException implements DescribesError
{
    public static function forCurrentAccount(): self
    {
        return new self('Модуль axiomasoft/corex-tasks не включён для текущего аккаунта.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(TasksErrorCode::TasksModuleDisabled, ErrorCategory::Unavailable, retryable: false);
    }
}
