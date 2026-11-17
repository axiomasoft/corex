<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Data\TransferRef;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
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
