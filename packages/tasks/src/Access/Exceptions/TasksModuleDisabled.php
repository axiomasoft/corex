<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Exceptions;

use CoreX\Tasks\Errors\Enums\TasksErrorCode;
use DomainException;
use Packman\Attributes\Api;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;

#[Api(since: '1.0')]
final class TasksModuleDisabled extends DomainException implements DescribesError
{
    public static function forCurrentAccount(): self
    {
        return new self('Модуль axiomasoft/corex-tasks не включён для текущего аккаунта.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TasksErrorCode::TasksModuleDisabled, category: ErrorCategory::Unavailable, retryable: false);
    }
}
