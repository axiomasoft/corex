<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class AccessDeniedException extends RuntimeException
{
    public function __construct(string $detail = 'Scoped write or read access denied.')
    {
        parent::__construct($detail);
    }
}
