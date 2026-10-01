<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Exceptions;

use CoreX\Attributes\Api;
use DomainException;

#[Api(since: '1.0')]
final class TasksModuleDisabled extends DomainException
{
    public static function forCurrentAccount(): self
    {
        return new self('Модуль axiomasoft/corex-tasks не включён для текущего аккаунта.');
    }
}
