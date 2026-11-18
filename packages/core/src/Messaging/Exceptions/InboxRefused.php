<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

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
        return new ErrorDescriptor(code: CoreErrorCode::InboxRefused, category: ErrorCategory::Conflict, retryable: false);
    }
}
