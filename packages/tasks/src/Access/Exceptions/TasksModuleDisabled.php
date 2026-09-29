<?php

declare(strict_types=1);

namespace CoreX\Tasks\Access\Exceptions;

use DomainException;

final class TasksModuleDisabled extends DomainException
{
    public static function forCurrentAccount(): self
    {
        return new self('Модуль axiomasoft/corex-tasks не включён для текущего аккаунта.');
    }
}
