<?php

declare(strict_types=1);

namespace Flex\Crm\Internal;

use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;

/** @internal */
final readonly class CrmSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'flex/crm', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/tenant', version: 1, drivers: ['pgsql', 'sqlite'], lifecycle: SchemaLifecycle::Module),
        ];
    }
}
