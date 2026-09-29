<?php

declare(strict_types=1);

namespace CoreX\Data\Exceptions;

use RuntimeException;

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
