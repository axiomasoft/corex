<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Support;

use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaScope;

final class FixtureTenantSchemaSets implements SchemaSetProvider
{
    public function sets(): array
    {
        return [new SchemaSet('axiomasoft/corex-tenancy', 'fixture', SchemaScope::Tenant, dirname(__DIR__).'/database/migrations/pg', 1)];
    }
}
