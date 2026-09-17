<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use InvalidArgumentException;

final readonly class OccurrencePage
{
    /** @param list<Occurrence> $occurrences */
    public function __construct(
        public array $occurrences,
        public string $status,
        public ?ExpansionCursor $nextCursor,
        public ?string $completeThrough,
        public int $scanned,
        public int $emitted,
        public string $definitionHash,
    ) {
        if (! in_array($status, ['complete', 'partial', 'unsupported', 'invalid'], true)) {
            throw new InvalidArgumentException('The occurrence page status is invalid.');
        }
    }
}
