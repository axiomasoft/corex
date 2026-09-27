<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

use InvalidArgumentException;

/** Server-owned ordering plus the signed tuple. Continuation is strict and skips the boundary row. */
final readonly class CursorPosition
{
    /**
     * @param  list<string>  $fields
     * @param  list<string>  $types
     * @param  list<string|int|float|bool|null>  $values
     */
    public function __construct(
        public string $ordering,
        public string $direction,
        public array $fields,
        public array $types,
        public array $values,
    ) {
        if ($this->fields === [] || count($this->fields) !== count($this->types) || count($this->fields) !== count($this->values)) {
            throw new InvalidArgumentException('Cursor position arity is invalid.');
        }
    }

    /**
     * @param  list<string|int|float|bool|null>  $candidate
     */
    public function admits(array $candidate): bool
    {
        if (count($candidate) !== count($this->values)) {
            throw new InvalidArgumentException('Cursor candidate arity is invalid.');
        }

        foreach ($this->values as $index => $boundary) {
            $comparison = self::compare($candidate[$index], $boundary, $this->types[$index]);

            if ($comparison === 0) {
                continue;
            }

            return $this->direction === 'asc' ? $comparison > 0 : $comparison < 0;
        }

        return false;
    }

    private static function compare(string|int|float|bool|null $candidate, string|int|float|bool|null $boundary, string $type): int
    {
        if ($type === 'string' && is_string($candidate) && is_string($boundary)) {
            return strcmp($candidate, $boundary);
        }

        if ($type === 'int' && is_int($candidate) && is_int($boundary)) {
            return $candidate <=> $boundary;
        }

        if ($type === 'float' && is_float($candidate) && is_float($boundary)) {
            return $candidate <=> $boundary;
        }

        if ($type === 'bool' && is_bool($candidate) && is_bool($boundary)) {
            return $candidate <=> $boundary;
        }

        throw new InvalidArgumentException('Cursor candidate type is invalid.');
    }
}
