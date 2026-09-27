<?php

declare(strict_types=1);

namespace CoreX\Api\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use InvalidArgumentException;

/** @internal Explicit schema connection boundary for shared budget and tenant API state. */
final readonly class ApiSchemaRegistrar
{
    public static function register(Application $app): void
    {
        $app->singleton(self::class, static function (Application $app): self {
            $schema = $app['config']->get('corex-api.schema');

            if (! is_array($schema)) {
                throw new InvalidArgumentException('Explicit corex-api.schema budget and tenant settings are required.');
            }

            $budget = self::identity($schema, 'budget');
            $tenant = self::identity($schema, 'tenant');

            return new self(
                budget: $app['db']->connection($budget['connection']),
                budgetDriver: $budget['driver'],
                budgetDatabase: $budget['database'],
                tenant: $app['db']->connection($tenant['connection']),
                tenantDriver: $tenant['driver'],
                tenantDatabase: $tenant['database'],
            );
        });
    }

    public function __construct(
        private Connection $budget,
        private string $budgetDriver,
        private string $budgetDatabase,
        private Connection $tenant,
        private string $tenantDriver,
        private string $tenantDatabase,
    ) {
        $this->assertIdentity($this->budget, $this->budgetDriver, $this->budgetDatabase, 'budget');
        $this->assertIdentity($this->tenant, $this->tenantDriver, $this->tenantDatabase, 'tenant');

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
            throw new InvalidArgumentException(sprintf('Explicit corex-api.schema.%s settings are required.', $scope));
        }

        $connection = $identity['connection'] ?? null;
        $driver = $identity['driver'] ?? null;
        $database = $identity['database'] ?? null;

        if (! is_string($connection) || $connection === ''
            || ! is_string($driver) || $driver === ''
            || ! is_string($database) || $database === '') {
            throw new InvalidArgumentException(sprintf('The corex-api.schema.%s connection, driver and database must be non-empty strings.', $scope));
        }

        return compact('connection', 'driver', 'database');
    }
}
