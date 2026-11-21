<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal;

use CoreX\Operations\Laravel\ApiSchemaConnections;
use Packman\Attributes\Internal;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaScope;

/** @internal */
#[Internal]
final readonly class OperationsSchemaSets implements SchemaSetProvider
{
    public function __construct(private ApiSchemaConnections $schema) {}

    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex-operations', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/tenant', version: 3, drivers: ['pgsql', 'sqlite']),
            new SchemaSet(package: 'axiomasoft/corex-operations', name: 'budget', scope: SchemaScope::Dedicated, path: $root.'/budget', version: 2, drivers: ['pgsql', 'sqlite'], connection: $this->schema->budget()->getName()),
        ];
    }
}
