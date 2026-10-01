<?php

declare(strict_types=1);

namespace CoreX\Data\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class InvalidScopePayload extends RuntimeException
{
    public static function unsupportedVersion(int $version): self
    {
        return new self("Unsupported data scope payload version [{$version}].");
    }

    public static function missing(string $key): self
    {
        return new self("Data scope payload key [{$key}] is missing or malformed.");
    }
}
