<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Internal;

use CoreX\Operations\Validation\JsonCanonical;
use InvalidArgumentException;
use JsonException;
use stdClass;

/** @internal Pretty bytes are a deterministic projection of JsonCanonical. */
final class SnapshotJson
{
    public static function pretty(mixed $value): string
    {
        $encoded = JsonCanonical::encode(self::normalize($value));

        try {
            $pretty = json_encode(
                json_decode($encoded, true, 512, JSON_THROW_ON_ERROR),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Snapshot JSON is not encodable.', previous: $exception);
        }

        return $pretty."\n";
    }

    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        $sorted = [];

        foreach ($value as $key => $item) {
            $sorted[(string) $key] = self::normalize($item);
        }

        ksort($sorted);

        return $sorted;
    }
}
