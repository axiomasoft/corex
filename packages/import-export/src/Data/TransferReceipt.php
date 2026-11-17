<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

/** @phpstan-import-type JsonMap from SourceRow */
#[Api(since: '1.0')]
final readonly class TransferReceipt
{
    /**
     * @param  JsonMap  $stats
     * @param  list<string>  $gaps
     */
    public function __construct(public string $state, public SourceCursor $cursor, public string $checkpointSequence, public array $stats, public array $gaps, public ?string $errorCode)
    {
        self::validateInput(state: $state, cursor: $cursor, checkpointSequence: $checkpointSequence, stats: $stats, gaps: $gaps, errorCode: $errorCode);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $stats
     * @param  array<array-key, mixed>  $gaps
     */
    private static function validateInput(/** Runtime input. */
        string $state, /** Runtime input. */
        SourceCursor $cursor, /** Runtime input. */
        string $checkpointSequence, /** Runtime input. */
        array $stats, /** Runtime input. */
        array $gaps, /** Runtime input. */
        ?string $errorCode): void
    {
        if (! in_array($state, ['pending', 'running', 'unknown', 'completed', 'cancelled', 'failed'], strict: true) || ! array_is_list($gaps)) {
            throw new InvalidArgumentException('Invalid transfer receipt.');
        }
        Validation::unsignedInteger(value: $checkpointSequence, name: 'checkpoint sequence');
        Validation::jsonMap(value: $stats, name: 'transfer stats');
        foreach ($gaps as $gap) {
            if (! is_string($gap)) {
                throw new InvalidArgumentException('Transfer gaps must be cursor ordinals.');
            }
            Validation::unsignedInteger(value: $gap, name: 'transfer gap');
        }

        if ($errorCode !== null) {
            Validation::opaque(value: $errorCode, name: 'transfer error code');
        }
    }
}
