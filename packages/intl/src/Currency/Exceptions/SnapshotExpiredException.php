<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class SnapshotExpiredException extends RuntimeException
{
    public function __construct(string $detail = 'Pinned reference snapshot is no longer available.')
    {
        parent::__construct($detail);
    }
}
