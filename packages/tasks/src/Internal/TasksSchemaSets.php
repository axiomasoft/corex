<?php

declare(strict_types=1);

namespace CoreX\Tasks\Internal;

use Packman\Attributes\Internal;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;

/** @internal */
#[Internal]
final readonly class TasksSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        $root = dirname(__DIR__, 2).'/database/migrations';

        return [
            new SchemaSet(package: 'axiomasoft/corex-tasks', name: 'tenant', scope: SchemaScope::Tenant, path: $root.'/tenant', version: 2, drivers: ['pgsql', 'sqlite'], lifecycle: SchemaLifecycle::Module),
        ];
    }
}
