<?php

declare(strict_types=1);

namespace CoreX\Database\Internal;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;

/** @internal */
final readonly class CoreSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        $root = dirname(__DIR__, 3).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/pg', version: 1),
            new SchemaSet(package: 'axiomasoft/corex', name: 'tenant-modules', scope: SchemaScope::Tenant, path: $root, version: 1),
        ];
    }
}
