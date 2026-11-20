<?php

declare(strict_types=1);

namespace Equipment\Registry;

use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;

final class EquipmentSchema implements SchemaSetProvider
{
    public function sets(): array
    {
        return [new SchemaSet(
            package: 'equipment/registry',
            name: 'tenant',
            scope: SchemaScope::Tenant,
            path: dirname(__DIR__).'/database/migrations',
            version: 1,
            lifecycle: SchemaLifecycle::Module,
        )];
    }
}
