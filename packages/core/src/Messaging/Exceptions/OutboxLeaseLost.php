<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use RuntimeException;

final class OutboxLeaseLost extends RuntimeException
{
    public static function superseded(string $messageId): self
    {
        return new self(
            "Outbox lease for message [{$messageId}] was superseded: the row was reclaimed by another relay. "
            .'The publish may already have reached its destination; do not finalize it here.',
        );
    }
}
