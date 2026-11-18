<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data\Export;

use CoreX\ImportExport\Data\CanonicalRecord;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ExportPage
{
    /** @param list<CanonicalRecord> $rows */
    public function __construct(public array $rows, public ?ExportCursor $next, public bool $complete)
    {
        self::validateInput(rows: $rows, next: $next, complete: $complete);
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $rows
     */
    private static function validateInput(
        /** Runtime input. */
        array $rows,
        /** Runtime input. */
        ?ExportCursor $next,
        /** Runtime input. */
        bool $complete,
    ): void {
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
