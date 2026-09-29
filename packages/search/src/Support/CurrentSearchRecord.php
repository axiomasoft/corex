<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

use CoreX\Data\RecordRef;
use InvalidArgumentException;

final readonly class CurrentSearchRecord
{
    /** @param array<array-key, mixed> $fields */
    public function __construct(
        public RecordRef $record,
        public string $schemaHash,
        public string $revision,
        public array $fields,
    ) {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $schemaHash) !== 1 || preg_match('/\A[a-f0-9]{64}\z/D', $revision) !== 1) {
            throw new InvalidArgumentException('Current search record requires immutable schema and revision hashes.');
        }

        foreach ($fields as $field => $value) {
            if (! is_string($field) || preg_match('/\A[a-z][a-z0-9_]{0,127}\z/D', $field) !== 1 || ! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Current search record contains an unsafe result field.');
            }
        }
    }
}
