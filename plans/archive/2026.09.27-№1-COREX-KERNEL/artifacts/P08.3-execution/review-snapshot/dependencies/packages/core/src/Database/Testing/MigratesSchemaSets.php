<?php

declare(strict_types=1);

namespace CoreX\Database\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Enums\SchemaScope;
use LogicException;

/**
 * Testbench helper: builds package tables through the same registry production uses,
 * so tests never name migration paths.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
trait MigratesSchemaSets
{
    /** @param list<string> $ids */
    protected function migrateSchemaSets(array $ids, ?string $connection = null): void
    {
        ($this->app ?? throw new LogicException('Boot the test application before migrating schema sets.'))->make(SchemaMigrator::class)->migrateSets($ids, $connection);
    }

    protected function migrateSchemaScope(SchemaScope $scope = SchemaScope::Tenant, ?string $connection = null, bool $includeModules = true): void
    {
        ($this->app ?? throw new LogicException('Boot the test application before migrating schema sets.'))->make(SchemaMigrator::class)->migrate($scope, $connection, $includeModules);
    }
}
