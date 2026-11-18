<?php

declare(strict_types=1);

namespace CoreX\Data\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class InvalidScopePayload extends CodedException
{
    public static function unsupportedVersion(int $version): self
    {
        return new self("Unsupported data scope payload version [{$version}].");
    }

    public static function missing(string $key): self
    {
        return new self("Data scope payload key [{$key}] is missing or malformed.");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::InvalidScopePayload, category: ErrorCategory::Validation, retryable: false);
    }
}
