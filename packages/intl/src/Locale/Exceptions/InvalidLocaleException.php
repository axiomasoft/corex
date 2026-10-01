<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class InvalidLocaleException extends RuntimeException
{
    public function __construct(string $detail = 'Invalid or unsupported BCP47 locale tag.')
    {
        parent::__construct($detail);
    }
}
