<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ApiSchemaConnections
{
    /**
     * Builds the schema connections from the explicit `corex-operations.schema` settings.
     *
     * @internal Composition helper of the operations package.
     */
    public static function fromSchema(mixed $schema, DatabaseManager $database): self
    {
        if (! is_array($schema)) {
            throw new InvalidArgumentException('Explicit corex-operations.schema budget and tenant settings are required.');
        }

        $budget = self::identity(schema: $schema, scope: 'budget');
        $tenant = self::identity(schema: $schema, scope: 'tenant');

        return new self(
            budget: $database->connection($budget['connection']),
            budgetDriver: $budget['driver'],
            budgetDatabase: $budget['database'],
            tenant: $database->connection($tenant['connection']),
            tenantDriver: $tenant['driver'],
            tenantDatabase: $tenant['database'],
        );
    }

    public function __construct(
        private Connection $budget,
        private string $budgetDriver,
        private string $budgetDatabase,
        private Connection $tenant,
        private string $tenantDriver,
        private string $tenantDatabase,
    ) {
        $this->assertIdentity(connection: $this->budget, driver: $this->budgetDriver, database: $this->budgetDatabase, scope: 'budget');
        $this->assertIdentity(connection: $this->tenant, driver: $this->tenantDriver, database: $this->tenantDatabase, scope: 'tenant');

        if ($this->budget === $this->tenant || $this->budget->getName() === $this->tenant->getName()) {
            throw new InvalidArgumentException('Budget and tenant schemas must use distinct explicit connections.');
        }
    }

    public function budget(): Connection
    {
        return $this->budget;
    }

    public function tenant(): Connection
    {
        return $this->tenant;
    }

    private function assertIdentity(Connection $connection, string $driver, string $database, string $scope): void
    {
        if ($driver === '' || $database === '') {
            throw new InvalidArgumentException(sprintf('The API %s schema driver and database identity are required.', $scope));
        }

        if ($connection->getDriverName() !== $driver || $connection->getDatabaseName() !== $database) {
            throw new InvalidArgumentException(sprintf('The API %s schema connection identity does not match its configuration.', $scope));
        }
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @return array{connection: string, driver: string, database: string}
     */
    private static function identity(array $schema, string $scope): array
    {
        $identity = $schema[$scope] ?? null;

        if (! is_array($identity)) {
            throw new InvalidArgumentException(sprintf('Explicit corex-operations.schema.%s settings are required.', $scope));
        }

        $connection = $identity['connection'] ?? null;
        $driver = $identity['driver'] ?? null;
        $database = $identity['database'] ?? null;

        if (! is_string($connection) || $connection === ''
            || ! is_string($driver) || $driver === ''
            || ! is_string($database) || $database === '') {
            throw new InvalidArgumentException(sprintf('The corex-operations.schema.%s connection, driver and database must be non-empty strings.', $scope));
        }

        return ['connection' => $connection, 'driver' => $driver, 'database' => $database];
    }
}
