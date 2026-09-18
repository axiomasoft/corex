<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use InvalidArgumentException;

/** Canonical tool-name/1 projection and validation. */
final class ToolName
{
    private const CANONICAL_PATTERN = '/^[a-z][a-z0-9]*\.[a-z][a-z0-9_]*$/';

    private const VERSION_PATTERN = '/^v[1-9][0-9]*$/';

    public static function assertCanonical(string $code): void
    {
        if (preg_match(self::CANONICAL_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException('Invalid canonical tool code: '.$code);
        }

        [, $verb] = explode('.', $code, 2);

        if (str_contains($verb, '__v')) {
            throw new InvalidArgumentException('Canonical tool verb reserves __v suffix: '.$code);
        }
    }

    public static function assertVersion(string $version): void
    {
        if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new InvalidArgumentException('Invalid tool version: '.$version);
        }
    }

    public static function wireName(string $code, string $version): string
    {
        self::assertCanonical($code);
        self::assertVersion($version);

        $versionNumber = substr($version, 1);

        return str_replace('.', '_', $code).'__v'.$versionNumber;
    }
}
