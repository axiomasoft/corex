<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use InvalidArgumentException;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final class FieldJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(self::sorted($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Canonicalize object order and JSON strings without decoding numeric lexemes through float. */
    public static function canonical(string $json): string
    {
        if (! json_validate($json)) {
            throw new InvalidArgumentException('Invalid JSON.');
        }
        preg_match_all('~"(?:[^"\\\\]|\\\\.)*"|[{}\[\],:]|[^\s{}\[\],:"]+~s', $json, $tokens);
        $position = 0;

        return self::read($tokens[0], $position);
    }

    /** @param list<string> $tokens */
    private static function read(array $tokens, int &$position): string
    {
        $token = $tokens[$position++];

        if ($token === '{') {
            $members = [];
            while ($tokens[$position] !== '}') {
                $key = self::encode(json_decode($tokens[$position++], flags: JSON_THROW_ON_ERROR));
                $position++; // validated colon

                if (array_key_exists($key, $members)) {
                    throw new InvalidArgumentException('Duplicate JSON member.');
                }
                $members[$key] = $key.':'.self::read($tokens, $position);

                if ($tokens[$position] === ',') {
                    $position++;
                }
            }
            $position++;
            ksort($members, SORT_STRING);

            return '{'.implode(',', $members).'}';
        }

        if ($token === '[') {
            $entries = [];
            while ($tokens[$position] !== ']') {
                $entries[] = self::read($tokens, $position);

                if ($tokens[$position] === ',') {
                    $position++;
                }
            }
            $position++;

            return '['.implode(',', $entries).']';
        }

        return str_starts_with($token, '"') ? self::encode(json_decode($token, flags: JSON_THROW_ON_ERROR)) : $token;
    }

    private static function sorted(mixed $value): mixed
    {
        if ($value instanceof stdClass || (is_array($value) && ! array_is_list($value))) {
            $items = (array) $value;
            ksort($items, SORT_STRING);

            return (object) array_map(self::sorted(...), $items);
        }

        if (is_array($value)) {
            return array_map(self::sorted(...), $value);
        }

        if ($value !== null && ! is_scalar($value)) {
            throw new InvalidArgumentException('Expected a JSON value.');
        }

        return $value;
    }
}
