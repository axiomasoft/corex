<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Support\Export;

use InvalidArgumentException;

final readonly class ExportCursor
{
    public function __construct(
        public string $partNumber,
        public string $rowOrdinal,
        public string $manifestHash,
    ) {
        if (! preg_match('/^(0|[1-9][0-9]*)$/', $partNumber)
            || ! preg_match('/^(0|[1-9][0-9]*)$/', $rowOrdinal)
            || ! preg_match('/^[a-f0-9]{64}$/', $manifestHash)) {
            throw new InvalidArgumentException('Export cursor is invalid.');
        }
    }
}
