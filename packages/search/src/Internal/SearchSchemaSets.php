<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;

/** @internal */
final readonly class SearchSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex-search', name: 'tenant', scope: SchemaScope::Tenant, path: $root, version: 1, drivers: ['pgsql', 'sqlite']),
        ];
    }
}
