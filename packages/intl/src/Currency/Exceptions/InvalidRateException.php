<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Exceptions;

use RuntimeException;

final class InvalidRateException extends RuntimeException
{
    public function __construct(string $detail = 'Invalid or malformed rate snapshot.')
    {
        parent::__construct($detail);
    }
}
