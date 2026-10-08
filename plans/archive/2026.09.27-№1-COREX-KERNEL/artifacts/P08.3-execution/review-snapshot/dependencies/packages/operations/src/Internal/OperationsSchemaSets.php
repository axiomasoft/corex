<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;

/** @internal */
final readonly class OperationsSchemaSets implements SchemaSetProvider
{
    public function __construct(private ApiSchemaRegistrar $schema) {}

    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex-operations', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/tenant', version: 1, drivers: ['pgsql', 'sqlite']),
            new SchemaSet(package: 'axiomasoft/corex-operations', name: 'budget', scope: SchemaScope::Dedicated, path: $root.'/budget', version: 1, drivers: ['pgsql', 'sqlite'], connection: $this->schema->budget()->getName()),
        ];
    }
}
