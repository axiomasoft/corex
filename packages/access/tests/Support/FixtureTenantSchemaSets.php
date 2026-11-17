<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Support;

use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaScope;

final class FixtureTenantSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        return [new SchemaSet('axiomasoft/corex-tenancy', 'fixture', SchemaScope::Tenant, dirname(__DIR__).'/database/migrations/pg', 1)];
    }
}
