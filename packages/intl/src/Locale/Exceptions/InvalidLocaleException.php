<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Exceptions;

use RuntimeException;

final class InvalidLocaleException extends RuntimeException
{
    public function __construct(string $detail = 'Invalid or unsupported BCP47 locale tag.')
    {
        parent::__construct($detail);
    }
}
