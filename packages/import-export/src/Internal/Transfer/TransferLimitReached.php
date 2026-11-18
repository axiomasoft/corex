<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Transfer;

use CoreX\ImportExport\Data\SourceCursor;
use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class TransferLimitReached extends RuntimeException
{
    /**
     * @param  array<string, int>  $stats
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly SourceCursor $cursor,
        public readonly string $checkpointSequence,
        public readonly array $stats,
    ) {
        parent::__construct($errorCode);
    }
}
