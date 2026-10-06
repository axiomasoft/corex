<?php

declare(strict_types=1);

namespace CoreX\Transfer\Internal;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;

/** @internal */
final readonly class TransferSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex-transfer', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/tenant', version: 1, drivers: ['pgsql', 'sqlite']),
        ];
    }
}
