<?php

declare(strict_types=1);

namespace CoreX\Intl\Exceptions;

use RuntimeException;

final class AccessDeniedException extends RuntimeException
{
    public function __construct(string $detail = 'Scoped write or read access denied.')
    {
        parent::__construct($detail);
    }
}
