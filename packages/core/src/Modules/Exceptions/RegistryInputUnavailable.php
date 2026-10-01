<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class RegistryInputUnavailable extends CodedException
{
    public static function missing(string $path): self
    {
        return new self(sprintf('Compiled module registry is unavailable at [%s].', $path));
    }

    public static function corrupt(): self
    {
        return new self('Compiled module registry payload is corrupt or unreadable.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::RegistryInputUnavailable, ErrorCategory::Unavailable, retryable: false);
    }
}
