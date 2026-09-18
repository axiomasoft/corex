<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

use InvalidArgumentException;

final readonly class RowBatch
{
    /** @param array<array-key, mixed> $rows */
    public function __construct(public array $rows, public SourceCursor $next, public bool $remaining)
    {
        if (! array_is_list($rows) || count($rows) > 500) {
            throw new InvalidArgumentException('Source batch must be a bounded list.');
        }
        foreach ($rows as $row) {
            if (! $row instanceof SourceRow || $row->sourceHash !== $next->sourceHash) {
                throw new InvalidArgumentException('Source batch rows must match its cursor source.');
            }
        }
    }
}
