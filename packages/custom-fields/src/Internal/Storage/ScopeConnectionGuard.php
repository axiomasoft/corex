<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Storage;

use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\Data\DataScope;
use Illuminate\Database\ConnectionInterface;

final readonly class ScopeConnectionGuard
{
    public function __construct(private ConnectionInterface $connection) {}

    public function assertMatches(DataScope $scope): void
    {
        // The owned engine is PostgreSQL-only: the database name is the physical tenant boundary.
        $actual = 'pgsql:'.$this->connection->getDatabaseName();

        if ($scope->connectionIdentity !== $actual) {
            throw new ScopeDenied('The attribute operation targets a different database connection.');
        }
    }
}
