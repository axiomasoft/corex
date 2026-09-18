<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

use InvalidArgumentException;

final readonly class TransferReceipt
{
    /**
     * @param  array<string, null|bool|int|string|array<array-key, mixed>>  $stats
     * @param  array<array-key, mixed>  $gaps
     */
    public function __construct(public string $state, public SourceCursor $cursor, public string $checkpointSequence, public array $stats, public array $gaps, public ?string $errorCode)
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
