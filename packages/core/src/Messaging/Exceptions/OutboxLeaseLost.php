<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class OutboxLeaseLost extends CodedException
{
    public static function superseded(string $messageId): self
    {
        return new self(
            "Outbox lease for message [{$messageId}] was superseded: the row was reclaimed by another relay. "
            .'The publish may already have reached its destination; do not finalize it here.',
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::OutboxLeaseLost, ErrorCategory::Conflict, retryable: false);
    }
}
