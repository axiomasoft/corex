<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

use CoreX\Api\Internal\JsonCanonical;
use InvalidArgumentException;

/** Canonical JSON object for tool schemas and arguments. */
final readonly class JsonObject
{
    public function __construct(
        public string $canonicalJson,
    ) {
        if ($canonicalJson === '') {
            throw new InvalidArgumentException('JsonObject requires non-empty canonical JSON.');
        }

        if ($canonicalJson !== '{}' && JsonCanonical::decodeObject($canonicalJson) === null) {
            throw new InvalidArgumentException('JsonObject root must be a JSON object.');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        if ($value === []) {
            return new self('{}');
        }

        return new self(JsonCanonical::encode($value));
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function fromSchemaArray(array $schema): self
    {
        return self::fromArray($schema);
    }
}
