<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class InvalidRegistryPoint extends CodedException
{
    public static function unknown(string $name): self
    {
        return new self(sprintf('Unknown registry extension point [%s].', $name));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::InvalidRegistryPoint, category: ErrorCategory::Validation, retryable: false);
    }
}
