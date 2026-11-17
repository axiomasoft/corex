<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class Validation
{
    public static function opaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\\x00-\\x1f\\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
    }

    public static function sha256(string $value, string $name): void
    {
        if (preg_match('/\\A[a-f0-9]{64}\\z/D', $value) !== 1) {
            throw new InvalidArgumentException("{$name} must be a lowercase SHA-256 digest.");
        }
    }

    public static function unsignedInteger(string $value, string $name): void
    {
        if (preg_match('/\\A(?:0|[1-9][0-9]{0,19})\\z/D', $value) !== 1) {
            throw new InvalidArgumentException("{$name} must be a canonical unsigned integer.");
        }
    }

    public static function utc(?DateTimeImmutable $value, string $name): void
    {
        if ($value !== null && $value->getTimezone()->getName() !== '+00:00' && $value->getTimezone()->getOffset($value) !== 0) {
            throw new InvalidArgumentException("{$name} must be UTC.");
        }
    }

    /** @param array<array-key, mixed> $value */
    public static function jsonMap(array $value, string $name, int $maxDepth = 16): void
    {
        if ($value !== [] && array_is_list($value)) {
            throw new InvalidArgumentException("{$name} must be a JSON object.");
        }

        $keys = 0;
        self::jsonValue(value: $value, name: $name, depth: 0, maxDepth: $maxDepth, keys: $keys);

        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("{$name} must be JSON-compatible.", previous: $exception);
        }

        if (strlen($encoded) > 65_536) {
            throw new InvalidArgumentException("{$name} exceeds 64 KiB.");
        }
    }

    /** @param array<array-key, mixed> $value */
    private static function jsonValue(array $value, string $name, int $depth, int $maxDepth, int &$keys): void
    {
        if ($depth > $maxDepth) {
            throw new InvalidArgumentException("{$name} exceeds JSON depth {$maxDepth}.");
        }

        foreach ($value as $nested) {
            if (++$keys > 256) {
                throw new InvalidArgumentException("{$name} exceeds 256 keys.");
            }

            if (is_null($nested) || is_bool($nested) || is_int($nested) || is_string($nested)) {
                continue;
            }

            if (! is_array($nested)) {
                throw new InvalidArgumentException("{$name} contains an unsupported JSON value.");
            }
            self::jsonValue(value: $nested, name: $name, depth: $depth + 1, maxDepth: $maxDepth, keys: $keys);
        }
    }
}
