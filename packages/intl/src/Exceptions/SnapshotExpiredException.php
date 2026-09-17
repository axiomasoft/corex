<?php

declare(strict_types=1);

namespace CoreX\Intl\Exceptions;

use RuntimeException;

final class SnapshotExpiredException extends RuntimeException
{
    public function __construct(string $detail = 'Pinned reference snapshot is no longer available.')
    {
        parent::__construct($detail);
    }
}
