<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class UnknownMessageContract extends CodedException
{
    public static function notDeclared(string $name, int $version): self
    {
        return new self(
            "Message contract [{$name}.v{$version}] is not declared: a message is published only by an "
            .'explicit registry declaration (MSG-002).',
        );
    }

    public static function duplicate(string $id): self
    {
        return new self("Message contract [{$id}] is declared more than once.");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::UnknownMessageContract, category: ErrorCategory::Validation, retryable: false);
    }
}
