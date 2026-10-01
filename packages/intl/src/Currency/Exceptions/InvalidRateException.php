<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class InvalidRateException extends RuntimeException
{
    public function __construct(string $detail = 'Invalid or malformed rate snapshot.')
    {
        parent::__construct($detail);
    }
}
