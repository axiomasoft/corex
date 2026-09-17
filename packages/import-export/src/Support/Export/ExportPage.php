<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Support\Export;

use CoreX\ImportExport\Support\CanonicalRecord;
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
