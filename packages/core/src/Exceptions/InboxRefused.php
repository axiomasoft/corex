<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

final class InboxRefused extends RuntimeException
{
    public static function outsideTransaction(string $consumer): self
    {
        return new self(
            "Inbox claim for consumer [{$consumer}] requires an open transaction: the deduplication row and "
            .'the business effect must commit together (MSG-006).',
        );
    }
}
