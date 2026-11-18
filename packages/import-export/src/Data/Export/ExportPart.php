<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data\Export;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ExportPart
{
    /** @param array<string, int> $rowCounts */
    public function __construct(
        public string $number,
        public string $immutableRef,
        public string $sha256,
        public int $byteLength,
        public array $rowCounts,
    ) {
        if (preg_match('/^(0|[1-9][0-9]*)$/', $number) !== 1
            || $immutableRef === ''
            || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
            || $byteLength < 0) {
            throw new InvalidArgumentException('Export part is invalid.');
        }
    }
}
