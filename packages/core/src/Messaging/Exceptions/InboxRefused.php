<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class InboxRefused extends CodedException
{
    public static function outsideTransaction(string $consumer): self
    {
        return new self(
            "Inbox claim for consumer [{$consumer}] requires an open transaction: the deduplication row and "
            .'the business effect must commit together (MSG-006).',
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::InboxRefused, ErrorCategory::Conflict, retryable: false);
    }
}
