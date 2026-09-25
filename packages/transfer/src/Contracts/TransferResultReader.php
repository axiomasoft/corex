<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Support\Data\DataScope;
use CoreX\Transfer\Data\TransferRef;

interface TransferResultReader
{
    /**
     * @return array{records: list<array{ordinal: string, code: string, path: ?string}>, next: ?string}
     */
    public function failures(DataScope $scope, TransferRef $ref, ?string $afterOrdinal, int $limit): array;

    /**
     * @return array<string, null|bool|int|string|array<array-key, mixed>>
     */
    public function result(DataScope $scope, TransferRef $ref): array;
}
