<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use InvalidArgumentException;

#[Api(since: '1.0')]
final class InvalidMessage extends InvalidArgumentException implements DescribesError
{
    public static function identity(string $name): self
    {
        return new self("Invalid message {$name}.");
    }

    public static function contractName(string $value): self
    {
        return new self("Message contract name [{$value}] must be a dotted lowercase name, not a PHP class name.");
    }

    public static function payloadShape(string $reason): self
    {
        return new self("Message payload {$reason}.");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::InvalidMessage, ErrorCategory::Validation, retryable: false);
    }
}
