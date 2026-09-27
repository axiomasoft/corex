<?php

declare(strict_types=1);

namespace CoreX\Intl\Exceptions;

use RuntimeException;

final class UnsupportedFormattingException extends RuntimeException
{
    public function __construct(string $detail = 'Requested formatting capability is unavailable.')
    {
        parent::__construct($detail);
    }
}
