<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Exceptions;

use RuntimeException;

final class OperationConflictException extends RuntimeException
{
    public function __construct(string $detail = 'Concurrent or conflicting operation.')
    {
        parent::__construct($detail);
    }
}
