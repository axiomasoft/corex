<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class OperationConflictException extends RuntimeException
{
    public function __construct(string $detail = 'Concurrent or conflicting operation.')
    {
        parent::__construct($detail);
    }
}
