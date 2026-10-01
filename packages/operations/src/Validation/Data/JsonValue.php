<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Data;

use CoreX\Attributes\Api;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use InvalidArgumentException;

/** Canonical JSON scalar or structured value for bounded schema validation. */
#[Api(since: '1.0')]
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

}
