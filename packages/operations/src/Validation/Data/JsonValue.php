<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Data;

use CoreX\Operations\Validation\JsonCanonical;
use InvalidArgumentException;

/** Canonical JSON scalar or structured value for bounded schema validation. */
final readonly class JsonValue
{
    public function __construct(
        public string $canonicalJson,
    ) {
        if ($canonicalJson === '') {
            throw new InvalidArgumentException('JsonValue requires non-empty canonical JSON.');
        }

        JsonCanonical::assertWellFormed($canonicalJson);
    }

    public static function fromDecoded(mixed $value): self
    {
        return new self(JsonCanonical::encode($value));
    }
}
