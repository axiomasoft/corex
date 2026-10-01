<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class SchemaMismatchException extends RuntimeException
{
    public function __construct(string $detail = 'Localized field or schema hash mismatch.')
    {
        parent::__construct($detail);
    }
}
