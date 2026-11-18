<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Support;

use CoreX\CustomFields\Data\ValueBag;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;

/** @phpstan-import-type JsonMap from ValueBag */
#[Internal]
final class Validation
{
    public static function entity(string $entity): void
    {
        if (preg_match('/\\A[a-z][a-z0-9._-]{0,127}\\z/D', $entity) !== 1) {
            throw new InvalidArgumentException('Entity must be a registered opaque handle.');
        }
    }

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

    /** @param array<array-key, mixed> $value */
    public static function jsonMap(array $value, string $name): void
    {
        self::assertMap(value: $value, name: $name);
    }

    /** @param array<array-key, mixed> $values */
    public static function jsonMapList(array $values, string $name): void
    {
        self::assertMapList(values: $values, name: $name);
    }

    /** @param array<array-key, mixed> $value
     * @return JsonMap
     */
    public static function validatedMap(array $value, string $name): array
    {
        self::assertMap(value: $value, name: $name);

        return $value;
    }

    /** @param array<array-key, mixed> $values
     * @return list<JsonMap>
     */
    public static function validatedMapList(array $values, string $name): array
    {
        self::assertMapList(values: $values, name: $name);

        return $values;
    }

    /** @param array<array-key, mixed> $values */
    public static function fieldList(array $values, int $limit = 30): void
    {
        if (! array_is_list($values) || $values === [] || count($values) > $limit) {
            throw new InvalidArgumentException("Fields must be a non-empty list of at most {$limit} unique codes.");
        }

        $fieldCodes = [];
        foreach ($values as $value) {
            if (! is_string($value) || preg_match('/\\A[a-z][a-z0-9._-]{0,127}\\z/D', $value) !== 1) {
                throw new InvalidArgumentException('Field code is invalid.');
            }

            $fieldCodes[] = $value;
        }

        if (count(array_unique($fieldCodes, SORT_STRING)) !== count($fieldCodes)) {
            throw new InvalidArgumentException('Field codes must be unique.');
        }
    }

    /** @param array<array-key, mixed> $value
     * @phpstan-assert JsonMap $value
     */
    private static function assertMap(array $value, string $name): void
    {
        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException("{$name} must have string keys.");
            }
        }
        $keys = 0;
        self::jsonValue(value: $value, name: $name, depth: 0, keys: $keys);

        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("{$name} must be JSON-compatible.", previous: $exception);
        }

        if (strlen($encoded) > 65536) {
            throw new InvalidArgumentException("{$name} must not exceed 64 KiB.");
        }
    }

    /** @param array<array-key, mixed> $values
     * @phpstan-assert list<JsonMap> $values
     */
    private static function assertMapList(array $values, string $name): void
    {
        if (! array_is_list($values)) {
            throw new InvalidArgumentException("{$name} must be a list.");
        }

        foreach ($values as $value) {
            if (! is_array($value) || array_is_list($value)) {
                throw new InvalidArgumentException("{$name} must contain JSON maps.");
            }

            self::assertMap(value: $value, name: $name);
        }
    }

    private static function jsonValue(mixed $value, string $name, int $depth, int &$keys): void
    {
        if ($depth > 16) {
            throw new InvalidArgumentException("{$name} exceeds JSON depth 16.");
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("{$name} contains an unsupported JSON value.");
        }

        foreach ($value as $nested) {
            if (++$keys > 256) {
                throw new InvalidArgumentException("{$name} exceeds 256 keys.");
            }

            self::jsonValue(value: $nested, name: $name, depth: $depth + 1, keys: $keys);
        }
    }
}
