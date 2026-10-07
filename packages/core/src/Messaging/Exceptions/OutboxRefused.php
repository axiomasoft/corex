<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class OutboxRefused extends CodedException
{
    public static function outsideTransaction(string $contract): self
    {
        return new self(
            "Outbox append for [{$contract}] requires an open transaction: the business change and the "
            .'outbox row must commit together (MSG-008).',
        );
    }

    public static function piiClassExceeded(string $contract, string $declared, string $actual): self
    {
        return new self(
            "Message [{$contract}] declares a maximum personal-data class of [{$declared}] but carries "
            ."[{$actual}] (MSG-007).",
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::OutboxRefused, category: ErrorCategory::Conflict, retryable: false);
    }
}
