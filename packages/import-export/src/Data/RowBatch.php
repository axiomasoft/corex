<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

/** @phpstan-import-type JsonMap from SourceRow */
#[Api(since: '1.0')]
final readonly class RowBatch
{
    /** @param list<SourceRow> $rows */
    public function __construct(public array $rows, public SourceCursor $next, public bool $remaining)
    {
        self::validateInput(rows: $rows, next: $next, remaining: $remaining);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $rows
     */
    private static function validateInput(/** Runtime input. */
        array $rows, /** Runtime input. */
        SourceCursor $next, /** Runtime input. */
        bool $remaining): void
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
