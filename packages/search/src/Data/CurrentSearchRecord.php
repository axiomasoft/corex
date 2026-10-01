<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\RecordRef;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class CurrentSearchRecord
{
    /** @param array<string, scalar|null> $fields */
    public function __construct(public RecordRef $record, public string $schemaHash, public string $revision, public array $fields)
    {
        self::validateInput($record, $schemaHash, $revision, $fields);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $fields
     */
    private static function validateInput(
        /** Runtime input. */
        RecordRef $record,
        /** Runtime input. */
        string $schemaHash,
        /** Runtime input. */
        string $revision,
        /** Runtime input. */
        array $fields,
    ): void {
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
