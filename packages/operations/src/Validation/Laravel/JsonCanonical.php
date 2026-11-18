<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Laravel;

use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Lossless canonical JSON encoding with duplicate-key rejection and safe numeric bounds. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class JsonCanonical
{
    public const int SAFE_INTEGER_MAX = 9007199254740991;

    public const int SAFE_INTEGER_MIN = -9007199254740991;

    public static function assertWellFormed(string $json): void
    {
        self::decode($json);
    }

    /**
     * @return array<array-key, mixed>|string|int|float|bool|null
     */
    public static function decode(string $json): array|string|int|float|bool|null
    {
        self::rejectDuplicateKeys($json);

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Malformed JSON.', previous: $exception);
        }

        self::assertSafeNumbers($decoded);

        if (! is_array($decoded) && ! is_scalar($decoded) && $decoded !== null) {
            throw new InvalidArgumentException('Unexpected JSON value.');
        }

        return $decoded;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function decodeObject(string $json): ?array
    {
        if ($json === '{}') {
            return [];
        }

        $decoded = self::decode($json);

        if ($decoded === null || ! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    public static function encode(mixed $value): string
    {
        self::assertSafeNumbers($value);

        try {
            return json_encode(self::sortKeys($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Value is not JSON-encodable.', previous: $exception);
        }
    }

    private static function rejectDuplicateKeys(string $json): void
    {
        $index = 0;
        self::parseValue(json: $json, index: $index);
        self::skipWhitespace(json: $json, index: $index);

        if ($index !== strlen($json)) {
            throw new InvalidArgumentException('Trailing JSON content rejected.');
        }
    }

    private static function parseValue(string $json, int &$index): void
    {
        self::skipWhitespace(json: $json, index: $index);
        $char = $json[$index] ?? '';

        match ($char) {
            '{' => self::parseObject(json: $json, index: $index),
            '[' => self::parseArray(json: $json, index: $index),
            '"' => self::parseString(json: $json, index: $index),
            default => self::parsePrimitive(json: $json, index: $index),
        };
    }

    private static function parseObject(string $json, int &$index): void
    {
        $index++;
        self::skipWhitespace(json: $json, index: $index);

        if (($json[$index] ?? '') === '}') {
            $index++;

            return;
        }

        $keys = [];

        while (true) {
            self::skipWhitespace(json: $json, index: $index);
            $key = self::parseString(json: $json, index: $index);

            if (isset($keys[$key])) {
                throw new InvalidArgumentException('Duplicate JSON object key: '.$key);
            }

            $keys[$key] = true;
            self::skipWhitespace(json: $json, index: $index);

            if (($json[$index] ?? '') !== ':') {
                throw new InvalidArgumentException('Malformed JSON object.');
            }

            $index++;
            self::parseValue(json: $json, index: $index);
            self::skipWhitespace(json: $json, index: $index);

            if (($json[$index] ?? '') === ',') {
                $index++;

                continue;
            }

            if (($json[$index] ?? '') === '}') {
                $index++;

                return;
            }

            throw new InvalidArgumentException('Malformed JSON object.');
        }
    }

    private static function parseArray(string $json, int &$index): void
    {
        $index++;
        self::skipWhitespace(json: $json, index: $index);

        if (($json[$index] ?? '') === ']') {
            $index++;

            return;
        }

        while (true) {
            self::parseValue(json: $json, index: $index);
            self::skipWhitespace(json: $json, index: $index);

            if (($json[$index] ?? '') === ',') {
                $index++;

                continue;
            }

            if (($json[$index] ?? '') === ']') {
                $index++;

                return;
            }

            throw new InvalidArgumentException('Malformed JSON array.');
        }
    }

    private static function parseString(string $json, int &$index): string
    {
        if (($json[$index] ?? '') !== '"') {
            throw new InvalidArgumentException('Expected JSON string.');
        }

        $index++;
        $buffer = '';
        $length = strlen($json);

        while ($index < $length) {
            $char = $json[$index];

            if ($char === '"') {
                $index++;

                return $buffer;
            }

            if ($char === '\\') {
                $index++;
                $escape = $json[$index] ?? '';
                $buffer .= match ($escape) {
                    '"', '\\', '/' => $escape,
                    'b' => "\x08",
                    'f' => "\x0C",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'u' => self::parseUnicodeEscape(json: $json, index: $index),
                    default => throw new InvalidArgumentException('Invalid JSON escape.'),
                };
                $index++;

                continue;
            }

            $buffer .= $char;
            $index++;
        }

        throw new InvalidArgumentException('Unterminated JSON string.');
    }

    private static function parseUnicodeEscape(string $json, int &$index): string
    {
        $hex = substr($json, $index + 1, 4);

        if (preg_match('/^[0-9a-fA-F]{4}$/', $hex) !== 1) {
            throw new InvalidArgumentException('Invalid JSON unicode escape.');
        }

        $index += 4;

        return mb_convert_encoding(pack('n', hexdec($hex)), 'UTF-8', 'UTF-16BE');
    }

    private static function parsePrimitive(string $json, int &$index): void
    {
        $remaining = substr($json, $index);

        if (preg_match('/^(true|false|null|-?\d+(?:\.\d+)?(?:[eE][+\-]?\d+)?)/', $remaining, $matches) !== 1) {
            throw new InvalidArgumentException('Malformed JSON primitive.');
        }

        $index += strlen($matches[1]);
    }

    private static function skipWhitespace(string $json, int &$index): void
    {
        while (isset($json[$index]) && in_array($json[$index], [' ', "\n", "\r", "\t"], true)) {
            $index++;
        }
    }

    private static function assertSafeNumbers(mixed $value): void
    {
        if (is_int($value) && ($value > self::SAFE_INTEGER_MAX || $value < self::SAFE_INTEGER_MIN)) {
            throw new InvalidArgumentException('JSON number outside safe integer range.');
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Non-finite JSON number rejected.');
            }

            if ($value > self::SAFE_INTEGER_MAX || $value < self::SAFE_INTEGER_MIN) {
                throw new InvalidArgumentException('JSON number outside safe integer range.');
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            self::assertSafeNumbers($item);
        }
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::sortKeys(...), $value);
        }

        $sorted = [];

        foreach (array_keys($value) as $key) {
            $sorted[(string) $key] = self::sortKeys($value[$key]);
        }

        ksort($sorted);

        return $sorted;
    }
}
