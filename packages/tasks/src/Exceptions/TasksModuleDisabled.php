<?php

declare(strict_types=1);

namespace CoreX\Tasks\Exceptions;

use DomainException;

final class TasksModuleDisabled extends DomainException
{
    public static function forCurrentAccount(): self
    {
        return new self('Модуль corex/tasks не включён для текущего аккаунта.');
    }
}
