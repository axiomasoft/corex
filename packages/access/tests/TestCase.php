<?php

declare(strict_types=1);

namespace CoreX\Access\Tests;

use CoreX\Access\Tests\Support\CoreAuthTestCase;

abstract class TestCase extends CoreAuthTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
