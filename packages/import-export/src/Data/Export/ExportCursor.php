<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data\Export;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ExportCursor
{
    public function __construct(
        public string $partNumber,
        public string $rowOrdinal,
        public string $manifestHash,
    ) {
        if (preg_match('/^(0|[1-9][0-9]*)$/D', $partNumber) !== 1
            || preg_match('/^(0|[1-9][0-9]*)$/D', $rowOrdinal) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $manifestHash) !== 1) {
            throw new InvalidArgumentException('Export cursor is invalid.');
        }
    }
}
