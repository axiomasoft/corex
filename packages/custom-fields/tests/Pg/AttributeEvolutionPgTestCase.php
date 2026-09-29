<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Pg;

use CoreX\CustomFields\Tests\PgTestCase;
use Illuminate\Support\Facades\Schema;
use Override;

class AttributeEvolutionPgTestCase extends PgTestCase
{
    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('sys_attribute_backfills');
        Schema::dropIfExists('sys_attribute_key_reservations');
        Schema::dropIfExists('sys_attribute_definitions');

        parent::defineDatabaseMigrations();
    }
}
