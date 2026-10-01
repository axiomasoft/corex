<?php

declare(strict_types=1);

namespace CoreX\Intl\Formatting\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class UnsupportedFormattingException extends RuntimeException
{
    public function __construct(string $detail = 'Requested formatting capability is unavailable.')
    {
        parent::__construct($detail);
    }
}
