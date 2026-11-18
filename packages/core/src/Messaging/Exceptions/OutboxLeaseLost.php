<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

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
        return new ErrorDescriptor(code: CoreErrorCode::OutboxLeaseLost, category: ErrorCategory::Conflict, retryable: false);
    }
}
