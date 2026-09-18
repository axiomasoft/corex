<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data\Export;

use CoreX\Transfer\Data\CanonicalRecord;
use InvalidArgumentException;

final readonly class ExportPage
{
    /** @param list<mixed> $rows */
    public function __construct(
        public array $rows,
        public ?ExportCursor $next,
        public bool $complete,
    ) {
        foreach ($rows as $row) {
            if (! $row instanceof CanonicalRecord) {
                throw new InvalidArgumentException('Export pages contain only canonical records.');
            }
        }

        if ($complete && $next !== null) {
            throw new InvalidArgumentException('Completed export pages cannot have a next cursor.');
        }
    }
}
