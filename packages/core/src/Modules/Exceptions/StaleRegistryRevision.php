<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class StaleRegistryRevision extends CodedException
{
    public static function mismatch(string $expected, string $actual): self
    {
        return new self(sprintf('Registry revision mismatch: expected [%s], current [%s].', $expected, $actual));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::StaleRegistryRevision, ErrorCategory::Conflict, retryable: false);
    }
}
