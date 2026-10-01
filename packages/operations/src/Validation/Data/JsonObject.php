<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Data;

use CoreX\Attributes\Api;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use InvalidArgumentException;

/** Canonical JSON object for tool schemas and arguments. */
#[Api(since: '1.0')]
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

}
