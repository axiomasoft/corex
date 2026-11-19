<?php

declare(strict_types=1);

namespace CoreX\Access\Internal;

use Packman\Attributes\Internal;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaScope;

/** @internal */
#[Internal]
final readonly class AccessSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex-access', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/tenant', version: 1),
            new SchemaSet(package: 'axiomasoft/corex-access', name: 'tenant-cloud', scope: SchemaScope::Tenant, path: $root.'/tenant-cloud', version: 1, requires: ['axiomasoft/corex-tenancy'], prerequisiteSets: ['axiomasoft/corex-access:tenant', 'axiomasoft/corex-tenancy:tenant']),
        ];
    }
}
