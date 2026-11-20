<?php

declare(strict_types=1);

namespace CoreX\Search\Postgres\Contracts;

use CoreX\Data\Data\DataScope;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface PostgresSnapshotAuthority
{
    /**
     * Proves that owner and policy reads share this connection and snapshot,
     * and that the mapped SQL corpus applies the whole record-read policy.
     */
    public function covers(DataScope $scope, ConnectionInterface $connection): bool;
}
