<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Internal;

use Packman\Attributes\Internal;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaScope;

/** @internal */
#[Internal]
final readonly class TenancySchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex-tenancy', name: 'root', scope: SchemaScope::Central, path: $root.'/root', version: 1),
            new SchemaSet(package: 'axiomasoft/corex-tenancy', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/tenant', version: 1, prerequisiteSets: ['axiomasoft/corex:tenant']),
        ];
    }
}
