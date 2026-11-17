<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\RecurringTasks\Exceptions\UnsupportedMaterialization;
use Illuminate\Database\Connection;

final readonly class ScopeConnectionGuard
{
    public function __construct(private Connection $connection) {}

    public function assertMatches(DataScope $scope): void
    {
        $driver = $this->connection->getDriverName();
        $database = $this->connection->getDatabaseName();

        $actual = match ($driver) {
            'pgsql' => 'pgsql:'.$database,
            'sqlite' => 'sqlite:'.$database,
            default => throw UnsupportedMaterialization::connection('The materialization connection is unsupported.'),
        };

        if ($scope->connectionIdentity !== $actual) {
            throw UnsupportedMaterialization::connection('The materialization operation targets a different database connection.');
        }
    }

    public static function identity(Connection $connection): string
    {
        return $connection->getDriverName().':'.$connection->getDatabaseName();
    }
}
