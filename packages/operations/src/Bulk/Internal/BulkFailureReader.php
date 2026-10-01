<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperation;
use InvalidArgumentException;

/** @internal Cursor page of safe bulk failure records. Limit 100 is mandatory. */
final readonly class BulkFailureReader
{
    public const int MAX_LIMIT = 100;

    /**
     * @param  callable(DataScope, BulkOperation, ?string, int):array{records: list<array{ordinal: string, code: string, path: ?string}>, next: ?string}  $page
     */
    public function __construct(private mixed $page) {}

    /**
     * @return array{records: list<array{ordinal: string, code: string, path: ?string}>, next: ?string}
     */
    public function read(DataScope $scope, BulkOperation $operation, ?string $afterOrdinal, int $limit): array
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException('Bulk failure page limit must be from 1 to 100.');
        }

        $page = ($this->page)($scope, $operation, $afterOrdinal, $limit);

        if (count($page['records']) > $limit) {
            throw new InvalidArgumentException('Bulk failure page exceeded the requested limit.');
        }

        return $page;
    }
}
