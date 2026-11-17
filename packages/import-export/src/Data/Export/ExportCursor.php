<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data\Export;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class ExportCursor
{
    public function __construct(
        public string $partNumber,
        public string $rowOrdinal,
        public string $manifestHash,
    ) {
        if (preg_match('/^(0|[1-9][0-9]*)$/', $partNumber) !== 1
            || preg_match('/^(0|[1-9][0-9]*)$/', $rowOrdinal) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $manifestHash) !== 1) {
            throw new InvalidArgumentException('Export cursor is invalid.');
        }
    }
}
